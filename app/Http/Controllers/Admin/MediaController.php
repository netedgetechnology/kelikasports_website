<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Support\ImageOptimizer;
use App\Support\MediaPicker;
use App\Support\MediaReplacement;
use App\Support\MediaUsage;
use App\Support\PublicSiteData;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MediaController extends Controller
{
    /** Detected MIME type → server-chosen file extension. Only these types can be stored. */
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

    private const MAX_IMAGE_SIDE = 6000;

    public function index(Request $request)
    {
        $query = MediaAsset::query()->latest();
        if ($request->filled('q')) {
            $term = $request->input('q');
            $query->where(fn ($builder) => $builder
                ->where('original_name', 'like', '%'.$term.'%')
                ->orWhere('alt_text', 'like', '%'.$term.'%')
                ->orWhere('caption', 'like', '%'.$term.'%'));
        }
        $media = $query->paginate(24)->withQueryString();
        // Records are kept even when their file is gone; the library flags them instead of showing a broken image.
        $missingFiles = $media->getCollection()
            ->reject(fn (MediaAsset $asset) => Storage::disk($asset->disk)->exists($asset->path))
            ->pluck('id')->all();
        $maxUpload = self::formatKb(self::maxUploadKb());
        $maxUploadBytes = self::maxUploadKb() * 1024;
        // Where each asset on this page is used: one query per source for all of them.
        $usage = MediaUsage::report($media->getCollection());
        $usageSources = MediaUsage::SOURCES;

        $trashedCount = MediaAsset::onlyTrashed()->count();

        return view('admin.media.index', compact('media', 'missingFiles', 'maxUpload', 'maxUploadBytes', 'usage', 'usageSources', 'trashedCount'));
    }

    /**
     * Deleted media, most recently deleted first. Deleting removes an asset's original and WebP copies from storage
     * (private replacement history is kept), so each row says plainly which files still exist.
     */
    public function trash()
    {
        $media = MediaAsset::onlyTrashed()->with('uploader:id,name')->latest('deleted_at')->orderByDesc('id')->paginate(24);
        $files = $media->getCollection()->mapWithKeys(fn (MediaAsset $asset) => [$asset->id => self::fileState($asset)]);

        return view('admin.media.trash', compact('media', 'files'));
    }

    /**
     * Brings a deleted asset's record back into the library, unchanged: same id, path, details and history. Files are
     * never recreated here; a missing original stays missing and is flagged in the library, ready to be replaced.
     */
    public function restore(MediaAsset $media)
    {
        abort_unless($media->trashed(), 404);

        $restored = DB::transaction(function () use ($media) {
            $asset = MediaAsset::onlyTrashed()->whereKey($media->id)->lockForUpdate()->first();
            if (! $asset) return null;
            // Two library records must never share one file.
            if (MediaAsset::query()->where('disk', $asset->disk)->where('path', $asset->path)->exists()) return false;
            $asset->restore();

            return $asset;
        });

        if ($restored === null) return redirect()->route('admin.media.trash')->withErrors(['media' => 'That asset has already been restored.']);
        if ($restored === false) return back()->withErrors(['media' => 'Another library asset already uses this file path, so this one was not restored.']);
        // Public pages resolve media on every request; clearing the shared cache keeps menus and footer in step too.
        PublicSiteData::forget();

        $state = self::fileState($restored);
        $message = match (true) {
            ! $state['original'] => "“{$restored->original_name}” was restored to the library, but its file is no longer in storage and could not be recovered. It is marked “File missing”: use Replace file on its card to upload it again (its address stays the same), or delete it again.",
            $state['variants_missing'] > 0 => "“{$restored->original_name}” was restored. Its original file is available, but {$state['variants_missing']} of its WebP copies are missing, so the website uses the original until they are regenerated (php artisan kelika:images --write) or the file is replaced.",
            default => "“{$restored->original_name}” was restored to the library with its files.",
        };

        return redirect()->route('admin.media.index', ['q' => $restored->original_name])->with('success', $message);
    }

    /** @return array{original: bool, variants_recorded: int, variants_missing: int, history: int} */
    private static function fileState(MediaAsset $asset): array
    {
        $disk = Storage::disk($asset->disk);
        $original = (bool) rescue(fn () => $asset->path !== '' && $disk->fileExists($asset->path), false, false);
        $variants = ($asset->metadata['optimization']['status'] ?? null) === ImageOptimizer::STATUS_OPTIMIZED && $asset->hasVariantsDirectory()
            ? array_column((array) ($asset->metadata['optimization']['variants'] ?? []), 'file') : [];
        $missing = count(array_filter($variants, fn ($file) => ! rescue(fn () => $disk->fileExists($asset->variantsDirectory().'/'.$file), false, false)));
        $history = count(rescue(fn () => Storage::disk('local')->directories('media-history/'.$asset->id), [], false));

        return ['original' => $original, 'variants_recorded' => count($variants), 'variants_missing' => $missing, 'history' => $history];
    }

    /**
     * Images for the section editor's picker, as JSON: supported image types only, newest first, searchable like the
     * library. Assets whose file is missing are left out, so a page can hold fewer than the page size.
     */
    public function picker(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = MediaPicker::query()->latest()->orderByDesc('id');
        if ($request->filled('q')) {
            $term = $request->input('q');
            $query->where(fn ($builder) => $builder
                ->where('original_name', 'like', '%'.$term.'%')
                ->orWhere('alt_text', 'like', '%'.$term.'%')
                ->orWhere('caption', 'like', '%'.$term.'%'));
        }
        $page = $query->simplePaginate(24);
        $items = $page->getCollection()
            ->filter(fn (MediaAsset $asset) => Storage::disk($asset->disk)->exists($asset->path))
            ->map(fn (MediaAsset $asset) => MediaPicker::option($asset))->values();

        return response()->json(['data' => $items, 'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['file' => self::fileRules(), 'alt_text' => ['nullable', 'string', 'max:250'], 'caption' => ['nullable', 'string', 'max:500']], self::fileMessages());

        $asset = self::storeUpload($data['file'], ['alt_text' => $data['alt_text'] ?? null, 'caption' => $data['caption'] ?? null], $request->user()->id);
        if (is_string($asset)) return back()->withErrors(['file' => $asset]);

        return self::optimizationOutcome(back(), $asset, 'Media uploaded.');
    }

    /**
     * Stores a validated upload as a new library asset: content checked by inspect(), a server-chosen file name, and
     * WebP copies for images. Used by the library and by settings that take an uploaded image (the admin logo).
     *
     * @return MediaAsset|string the saved asset, or a message saying why nothing was kept
     * @throws ValidationException when the file's content is not an accepted type
     */
    public static function storeUpload(UploadedFile $file, array $attributes, ?int $userId): MediaAsset|string
    {
        $inspected = self::inspect($file);
        $filename = Str::uuid().'.'.$inspected['extension'];
        $path = $file->storeAs('media/'.now()->format('Y/m'), $filename, 'public');
        if (! $path) return 'The file could not be stored. Please try again.';

        $asset = new MediaAsset([
            'disk' => 'public',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 240, ''),
            'mime_type' => $inspected['mime_type'],
            'size_bytes' => $file->getSize(),
            'alt_text' => $attributes['alt_text'] ?? null,
            'caption' => $attributes['caption'] ?? null,
            'metadata' => ['sha1' => sha1_file(Storage::disk('public')->path($path))] + (array) $inspected['metadata'],
            'uploaded_by' => $userId,
        ]);

        // Copies are made before the record exists. If saving fails, the file and its copies are removed again,
        // so an upload never leaves a record without a file or files without a record.
        try {
            if ($asset->isImage()) $asset->metadata = $asset->optimizedMetadata();
            $asset->save();
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            if ($asset->hasVariantsDirectory()) Storage::disk('public')->deleteDirectory($asset->variantsDirectory());
            report($exception);

            return 'The upload could not be saved, so nothing was kept. Please try again.';
        }

        return $asset;
    }

    /**
     * Replaces the file of an existing asset. The record, path and every reference to it stay the same; the previous
     * file and copies are kept in private history (see App\Support\MediaReplacement).
     */
    public function replace(Request $request, MediaAsset $media)
    {
        $data = $request->validate(['file' => self::fileRules()], self::fileMessages());
        $inspected = self::inspect($data['file']);
        if ($inspected['mime_type'] !== $media->mime_type) {
            $kind = strtoupper(pathinfo($media->path, PATHINFO_EXTENSION));
            throw ValidationException::withMessages(['file' => "Replace this {$kind} with another {$kind} file, so every page that uses its address keeps working."]);
        }
        if (! $media->hasVariantsDirectory()) return back()->withErrors(['file' => 'This asset has an unexpected storage path and cannot be replaced safely.']);

        try {
            MediaReplacement::run($media, $data['file'], $request->user()->id);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['file' => 'The replacement could not be completed, so the previous file was kept. Please try again.']);
        }

        return self::optimizationOutcome(back(), $media->fresh(), 'Media replaced. Pages using it now show the new file.');
    }

    /** Success message, plus a clear warning whenever an image could not be optimised. */
    private static function optimizationOutcome($response, MediaAsset $asset, string $message)
    {
        $optimization = $asset->metadata['optimization'] ?? null;
        if (! $asset->isImage() || ! is_array($optimization)) return $response->with('success', $message);

        return match ($optimization['status']) {
            ImageOptimizer::STATUS_OPTIMIZED => $response->with('success', $message.' '.$asset->optimizationLabel().'.'),
            ImageOptimizer::STATUS_TOO_LARGE => $response->with('success', $message)->withErrors(['optimization' => 'This image is too large to optimise ('.$optimization['error'].'). The original file is used as it is.']),
            default => (function () use ($response, $asset, $message, $optimization) {
                report(new RuntimeException("Media #{$asset->id} could not be optimised: ".$optimization['error']));

                return $response->with('success', $message)->withErrors(['optimization' => 'Optimised copies could not be created, so the original file is used as it is. The error has been logged.']);
            })(),
        };
    }

    /** Upload rules for library files; $imagesOnly leaves out PDFs (for image settings such as the admin logo). */
    public static function fileRules(bool $imagesOnly = false, bool $required = true): array
    {
        $types = $imagesOnly ? array_values(array_filter(array_keys(self::EXTENSIONS), fn ($mime) => str_starts_with($mime, 'image/'))) : array_keys(self::EXTENSIONS);

        return [$required ? 'required' : 'nullable', 'file', 'max:'.self::maxUploadKb(), 'mimes:'.($imagesOnly ? 'jpg,jpeg,png,webp' : 'jpg,jpeg,png,webp,pdf'), 'mimetypes:'.implode(',', $types)];
    }

    public static function fileMessages(string $field = 'file'): array
    {
        $limit = self::formatKb(self::maxUploadKb());

        return ["{$field}.uploaded" => "The file could not be uploaded. Files must be {$limit} or smaller.", "{$field}.max" => "Files must be {$limit} or smaller."];
    }

    /**
     * Server-side checks on the file's actual content. The stored extension comes from the detected MIME type, never
     * from the browser-supplied filename, so a file named "x.php" can only ever be stored as .jpg/.png/.webp/.pdf.
     * Images must decode, match their detected type and stay within MAX_IMAGE_SIDE pixels per side.
     *
     * @return array{extension: string, mime_type: string, metadata: ?array}
     */
    public static function inspect(UploadedFile $file): array
    {
        $mime = (string) $file->getMimeType();
        $extension = self::EXTENSIONS[$mime] ?? null;
        if (! $extension) throw ValidationException::withMessages(['file' => 'Unsupported file type. Upload a JPG, PNG, WebP or PDF file.']);

        $metadata = null;
        if (str_starts_with($mime, 'image/')) {
            $image = @getimagesize($file->getRealPath());
            if (! $image || ($image['mime'] ?? null) !== $mime || $image[0] < 1 || $image[1] < 1) {
                throw ValidationException::withMessages(['file' => 'The image could not be read. Re-export it as JPG, PNG or WebP and try again.']);
            }
            if ($image[0] > self::MAX_IMAGE_SIDE || $image[1] > self::MAX_IMAGE_SIDE) {
                throw ValidationException::withMessages(['file' => 'Images must be at most '.self::MAX_IMAGE_SIDE.' × '.self::MAX_IMAGE_SIDE.' pixels.']);
            }
            $metadata = ['width' => $image[0], 'height' => $image[1]];
        }

        return ['extension' => $extension, 'mime_type' => $mime, 'metadata' => $metadata];
    }

    /** Effective upload limit in KB: the CMS's 8 MB ceiling or PHP's upload_max_filesize, whichever is lower. */
    public static function maxUploadKb(): int
    {
        return (int) floor(min(8192 * 1024, UploadedFile::getMaxFilesize()) / 1024);
    }

    public static function formatKb(int $kb): string
    {
        return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.').' MB' : $kb.' KB';
    }

    public function update(Request $request, MediaAsset $media)
    {
        $data = $request->validate([
            'original_name' => ['required', 'string', 'max:240'],
            'alt_text' => ['nullable', 'string', 'max:250'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);
        $media->update($data);

        return back()->with('success', 'Media details saved.');
    }

    public function destroy(MediaAsset $media)
    {
        // Check references and archive the record in one transaction; any failure rolls back and keeps the file.
        try {
            $usages = DB::transaction(function () use ($media) {
                $usages = $media->usages();
                if ($usages === []) $media->delete();

                return $usages;
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['media' => 'This asset could not be safely confirmed as unused and deleted, so nothing was changed. Please try again.']);
        }

        if ($usages !== []) {
            $listed = implode('; ', array_slice($usages, 0, 5)).(count($usages) > 5 ? '; and '.(count($usages) - 5).' more' : '');

            return back()->withErrors(['media' => "This asset is still in use: {$listed}. Replace these references before deleting it."]);
        }

        // Only now, with the record archived, remove the file. If that fails the file stays and the problem is logged.
        $disk = Storage::disk($media->disk);
        // Its WebP copies are derived from the original and go with it (history from earlier replacements is kept).
        if ($media->hasVariantsDirectory()) rescue(fn () => $disk->deleteDirectory($media->variantsDirectory()));
        if (! rescue(fn () => $disk->delete($media->path), false) && rescue(fn () => $disk->exists($media->path), true)) {
            report(new RuntimeException("Media #{$media->id} was archived but its file could not be deleted: {$media->path}"));

            return back()->with('success', 'Media asset removed from the library, but its file could not be deleted from storage. The error has been logged.');
        }

        return back()->with('success', 'Media asset deleted.');
    }
}

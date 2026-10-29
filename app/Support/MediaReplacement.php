<?php

namespace App\Support;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Replaces a media asset's file while keeping its record and path, so every page, setting and menu that refers to
 * the path keeps working.
 *
 * The previous original and its WebP copies are kept in private history (storage/app/private/media-history/{id}/…,
 * which is not reachable by URL) and are never deleted automatically. Every step can be undone: the original is
 * copied to history before anything changes, the new file replaces it with one atomic rename (so the URL never
 * points to a missing file), the new copies get new names next to the old ones, and only once the record has been
 * saved are the old copies moved away. If the swap or the save fails, the previous file and record are restored.
 */
class MediaReplacement
{
    /** @return array The optimisation result for the new file. */
    public static function run(MediaAsset $media, UploadedFile $file, ?int $userId): array
    {
        $disk = Storage::disk($media->disk);
        $private = Storage::disk('local');
        $originalPath = $disk->path($media->path);
        $variantsDir = $disk->path($media->variantsDirectory());
        $historyRelative = 'media-history/'.$media->id.'/'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
        $historyDir = $private->path($historyRelative);
        $stagingDir = $private->path('media-staging');
        $oldVariants = array_column((array) ($media->metadata['optimization']['variants'] ?? []), 'file');
        $oldAttributes = $media->only(['path', 'mime_type', 'size_bytes', 'original_name', 'metadata']);
        $staged = null;
        $newVariants = [];
        $swapped = false;

        try {
            foreach ([$historyDir, $stagingDir] as $dir) {
                if (! is_dir($dir) && ! @mkdir($dir, 0750, true) && ! is_dir($dir)) throw new RuntimeException('Could not prepare private storage for the replacement.');
            }

            // 1. Keep the current original and record in private history (a copy: the live file stays in place).
            $hadOriginal = is_file($originalPath);
            if ($hadOriginal && ! @copy($originalPath, $historyDir.'/'.basename($originalPath))) throw new RuntimeException('Could not copy the current file to history.');
            file_put_contents($historyDir.'/record.json', json_encode($oldAttributes + ['id' => $media->id, 'replaced_at' => now()->toIso8601String(), 'replaced_by' => $userId, 'had_file' => $hadOriginal], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // 2. Stage the upload privately, then create its copies beside the current ones (different names, nothing overwritten).
            $staged = $file->move($stagingDir, Str::uuid().'.'.pathinfo($media->path, PATHINFO_EXTENSION))->getPathname();
            $sha1 = sha1_file($staged);
            $optimization = null;
            if ($media->isImage()) {
                $optimization = ImageOptimizer::process($staged, $variantsDir, false);
                $newVariants = array_values(array_diff(array_column($optimization['variants'], 'file'), $oldVariants));
            }

            // 3. Swap the original in one atomic step.
            if (! @rename($staged, $originalPath)) throw new RuntimeException('Could not put the new file in place.');
            $swapped = true;
            @chmod($originalPath, 0644);

            // 4. Save the record.
            $metadata = ['sha1' => $sha1];
            if ($optimization) {
                $metadata['optimization'] = $optimization;
                if ($optimization['width'] && $optimization['height']) $metadata += ['width' => $optimization['width'], 'height' => $optimization['height']];
            }
            DB::transaction(fn () => $media->update([
                'size_bytes' => filesize($originalPath),
                'original_name' => Str::limit($file->getClientOriginalName(), 240, ''),
                'metadata' => $metadata,
            ]));
        } catch (Throwable $exception) {
            // Put everything back as it was: previous file, previous record (unchanged on failure), no new copies.
            $restored = true;
            if ($swapped && isset($hadOriginal)) {
                $restored = $hadOriginal ? self::restore($historyDir.'/'.basename($originalPath), $originalPath) : @unlink($originalPath);
            }
            foreach ($newVariants as $name) @unlink($variantsDir.'/'.$name);
            if ($staged && is_file($staged)) @unlink($staged);
            $media->refresh();
            // History is only discarded when nothing needed it; if the restore failed it still holds the previous file.
            if ($restored) $private->deleteDirectory($historyRelative);

            throw $exception;
        }

        // 5. The new file and record are live: move the previous copies into history (they are no longer referenced).
        $kept = array_column($optimization['variants'] ?? [], 'file');
        foreach (array_diff($oldVariants, $kept) as $name) {
            if (! is_file($variantsDir.'/'.$name)) continue;
            if (! is_dir($historyDir.'/variants')) @mkdir($historyDir.'/variants', 0750);
            if (! @rename($variantsDir.'/'.$name, $historyDir.'/variants/'.$name)) {
                report(new RuntimeException("Media #{$media->id}: previous copy {$name} could not be moved to history; it is unused and can be removed."));
            }
        }

        return $optimization ?? [];
    }

    /** Copy back from history and rename into place, so the live path is replaced atomically. */
    private static function restore(string $from, string $to): bool
    {
        $temp = dirname($to).'/.restore-'.bin2hex(random_bytes(6));
        if (@copy($from, $temp) && @rename($temp, $to)) return true;
        @unlink($temp);
        report(new RuntimeException("Could not restore {$to} from {$from}; the previous file is still in history."));

        return false;
    }
}

<?php

namespace App\Models;

use App\Support\ImageOptimizer;
use App\Support\MediaUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'alt_text', 'caption', 'metadata', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** Public URL of the original, versioned by its content so a replaced file is never shown from a stale cache. */
    public function getUrlAttribute(): string
    {
        $url = Storage::disk($this->disk)->url($this->path);
        $version = $this->contentVersion();

        return $version ? $url.'?v='.$version : $url;
    }

    public function contentVersion(): ?string
    {
        $sha1 = $this->metadata['sha1'] ?? $this->metadata['optimization']['source_sha1'] ?? null;

        return is_string($sha1) && $sha1 !== '' ? substr($sha1, 0, 10) : null;
    }

    /** True when the path ends in "name.ext", so its copies folder ("name") is a folder of its own. */
    public function hasVariantsDirectory(): bool
    {
        return (bool) preg_match('/\/[^\/]+\.[A-Za-z0-9]+$/', (string) $this->path);
    }

    /**
     * Stored size for the library, e.g. "245 KB" or "2.4 MB": the size recorded at upload or replacement, or the
     * file's actual size when none was recorded. Null when neither is known (for example, the file is missing).
     */
    public function sizeLabel(): ?string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes < 1) {
            try {
                $bytes = Storage::disk($this->disk)->fileExists($this->path) ? (int) Storage::disk($this->disk)->size($this->path) : 0;
            } catch (\Throwable) {
                $bytes = 0;
            }
        }

        return $bytes > 0 ? self::formatBytes($bytes) : null;
    }

    /** Human-readable size in binary units, matching the upload form's own display (resources/js/app.js). */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes.' bytes';
        if ($bytes < 1048576) return round($bytes / 1024).' KB';

        return round($bytes / 1048576, 1).' MB';
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /** Folder (on the asset's disk) holding the WebP copies: the original's path without its extension. */
    public function variantsDirectory(): string
    {
        return preg_replace('/\.[^.\/]+$/', '', $this->path);
    }

    /**
     * Generates the WebP copies for the current original and returns the metadata to store with it. Nothing is
     * saved here, so callers can store the record and its optimisation state together.
     */
    public function optimizedMetadata(bool $removeStale = true): array
    {
        $disk = Storage::disk($this->disk);
        $result = ImageOptimizer::process($disk->path($this->path), $disk->path($this->variantsDirectory()), $removeStale);
        $metadata = (array) ($this->metadata ?? []);
        if ($result['width'] && $result['height']) $metadata = ['width' => $result['width'], 'height' => $result['height']] + $metadata;
        if ($result['source_sha1']) $metadata = ['sha1' => $result['source_sha1']] + $metadata;

        return ['optimization' => $result] + $metadata;
    }

    /** Short status for the media library, e.g. "Optimised · 4 sizes" or "Not optimised: too large". */
    public function optimizationLabel(): ?string
    {
        if (! $this->isImage()) return null;
        $optimization = $this->metadata['optimization'] ?? null;
        if (! is_array($optimization)) return 'Not optimised yet';

        return match ($optimization['status'] ?? null) {
            ImageOptimizer::STATUS_OPTIMIZED => count($optimization['variants'] ?? []) > 0
                ? 'Optimised · '.count($optimization['variants']).' '.\Illuminate\Support\Str::plural('size', count($optimization['variants']))
                : 'Optimised · original is already efficient',
            ImageOptimizer::STATUS_TOO_LARGE => 'Not optimised: too large to process (original is used)',
            default => 'Not optimised: processing failed (original is used)',
        };
    }

    /**
     * Places that still reference this asset, as readable labels. Empty means nothing uses it.
     *
     * References are stored in several forms: the media/… path, a /storage/… or absolute URL, inside JSON with or
     * without escaped slashes, or as pages.og_media_id. The server-generated file name appears verbatim in every
     * text form, so matching on it finds them all. Archived pages and content items count, since they can return.
     * Page revisions are history, not live references, and are not checked.
     *
     * @return list<string>
     * @throws RuntimeException when there is no usable file name; callers must then treat the asset as in use.
     */
    public function usages(): array
    {
        $report = MediaUsage::report([$this])[$this->id];
        if ($report['state'] === MediaUsage::UNKNOWN) {
            throw new RuntimeException("Media #{$this->id} has no usable file name to check references against.");
        }

        // Mentions count too: deletion is blocked whenever the file name appears anywhere that was checked.
        return array_column($report['uses'], 'label');
    }

    /**
     * Thumbnail for the media library: the smallest optimised WebP copies (400px, plus 800px for sharp screens) when
     * they exist on disk, otherwise the original. Null for documents and for images whose original is missing.
     *
     * @return array{src: string, srcset: ?string, original: bool}|null
     */
    public function preview(): ?array
    {
        if (! $this->isImage()) return null;
        $disk = Storage::disk($this->disk);
        $variants = collect($this->metadata['optimization']['variants'] ?? [])
            ->filter(fn ($v) => ($this->metadata['optimization']['status'] ?? null) === ImageOptimizer::STATUS_OPTIMIZED && $v['width'] <= 800
                && $this->hasVariantsDirectory() && $disk->exists($this->variantsDirectory().'/'.$v['file']))
            ->sortBy('width')->take(2)->values();

        if ($variants->isNotEmpty()) {
            $url = fn ($v) => $disk->url($this->variantsDirectory().'/'.rawurlencode($v['file']));

            return ['src' => $url($variants[0]), 'srcset' => $variants->map(fn ($v) => $url($v).' '.$v['width'].'w')->implode(', '), 'original' => false];
        }

        return $disk->exists($this->path) ? ['src' => $this->url, 'srcset' => null, 'original' => true] : null;
    }
}

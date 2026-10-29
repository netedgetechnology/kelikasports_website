<?php

namespace App\Support;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Which media library assets an editor can pick as a section image, and what the picker shows for them.
 *
 * Only images in the formats the library accepts, whose original file is still on disk, are selectable. A picked
 * image is always stored as the asset's original media/… path, the same value editors used to paste by hand, so
 * public templates and the WebP copies (looked up by that path) keep working unchanged.
 */
class MediaPicker
{
    /** The image types the media library stores (see MediaController::EXTENSIONS); PDFs are never selectable. */
    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const COLUMNS = ['id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'alt_text', 'metadata', 'created_at'];

    public static function query(): Builder
    {
        return MediaAsset::query()->whereIn('mime_type', self::MIME_TYPES)->select(self::COLUMNS);
    }

    /** The asset with this id, or null when it does not exist, is not a supported image, or its file is missing. */
    public static function find(mixed $id): ?MediaAsset
    {
        if (! is_numeric($id) || (int) $id != $id || (int) $id < 1) return null;
        $asset = self::query()->find((int) $id);

        return $asset && self::fileExists($asset) ? $asset : null;
    }

    /**
     * Of the given media/… paths, those belonging to a selectable asset. One query for all of them.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    public static function selectablePaths(array $paths): array
    {
        $paths = array_values(array_unique(array_filter($paths, 'is_string')));
        if (! $paths) return [];

        return self::query()->whereIn('path', $paths)->get()
            ->filter(fn (MediaAsset $asset) => self::fileExists($asset))
            ->pluck('path')->unique()->values()->all();
    }

    /** What the picker grid shows for one asset; the thumbnail is a small WebP copy whenever one exists. */
    public static function option(MediaAsset $asset): array
    {
        $preview = $asset->preview();

        return [
            'id' => $asset->id,
            'path' => $asset->path,
            'name' => $asset->original_name ?: basename($asset->path),
            'alt' => (string) $asset->alt_text,
            'width' => $asset->metadata['width'] ?? null,
            'height' => $asset->metadata['height'] ?? null,
            'type' => strtoupper(pathinfo($asset->path, PATHINFO_EXTENSION)),
            'size' => $asset->sizeLabel(),
            'uploaded' => $asset->created_at?->format('j M Y'),
            'thumb' => $preview['src'] ?? null,
            'thumb_srcset' => $preview['srcset'] ?? null,
        ];
    }

    /**
     * Previews of the images a section currently uses, keyed by stored value: picker details for media library
     * paths, a small rendition for other paths and URLs, and a warning when a media/… path has no usable asset.
     *
     * @param  list<string>  $values
     * @return array<string, array>
     */
    public static function previews(array $values): array
    {
        $values = array_values(array_unique(array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : '', $values))));
        $mediaPaths = array_values(array_filter($values, fn ($v) => str_starts_with($v, 'media/')));
        $assets = $mediaPaths
            ? self::query()->whereIn('path', $mediaPaths)->get()->filter(fn (MediaAsset $asset) => self::fileExists($asset))->keyBy('path')
            : collect();

        $previews = [];
        foreach ($values as $value) {
            if (isset($assets[$value])) {
                $previews[$value] = ['source' => 'library'] + self::option($assets[$value]);
            } elseif (str_starts_with($value, 'media/')) {
                $previews[$value] = ['source' => 'missing', 'name' => basename($value), 'thumb' => null, 'thumb_srcset' => null];
            } else {
                $image = Media::responsive($value);
                $previews[$value] = ['source' => 'custom', 'name' => Str::limit($value, 80), 'thumb' => $image['src'] ?? null,
                    'thumb_srcset' => $image['webp'] ?? $image['srcset'] ?? null];
            }
        }

        return $previews;
    }

    /** Every image value stored in section data, at the top level and inside repeatable items. */
    public static function imageValues(array $data): array
    {
        $values = [];
        array_walk_recursive($data, function ($value, $key) use (&$values) {
            if (is_string($value) && in_array((string) $key, ['image', 'image_path', 'mobile_image'], true) && trim($value) !== '') $values[] = trim($value);
        });

        return array_values(array_unique($values));
    }

    public static function fileExists(MediaAsset $asset): bool
    {
        return Storage::disk($asset->disk)->exists($asset->path);
    }
}

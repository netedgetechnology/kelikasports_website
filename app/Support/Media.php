<?php

namespace App\Support;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

class Media
{
    /** Where WebP copies of files under public/images are written, and their manifest. */
    public const STATIC_VARIANTS_DIR = 'images/optimized';

    /** Unsplash images are resized by Unsplash itself through the "w" query parameter. */
    private const UNSPLASH_HOST = 'images.unsplash.com';

    /**
     * Resolve a CMS image value (absolute URL, root-relative path, or media library path) to a public URL.
     * Media library files carry a content version, so a replaced image is never served from a stale browser cache.
     */
    public static function url(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (str_starts_with($value, 'http') || str_starts_with($value, '/')) return $value;

        $url = Storage::disk('public')->url($value);
        $version = self::mediaAsset($value)?->contentVersion();

        return $version ? $url.'?v='.$version : $url;
    }

    /**
     * Everything needed for responsive markup: the fallback URL, an optional WebP srcset (our own copies),
     * an optional srcset in the original format (Unsplash widths), and intrinsic dimensions when known.
     * Missing copies are left out, so the original is always a working fallback.
     *
     * @return array{src: string, webp: ?string, srcset: ?string, width: ?int, height: ?int}|null
     */
    public static function responsive(?string $value): ?array
    {
        $src = self::url($value);
        if ($src === null) return null;
        $value = trim((string) $value);
        $result = ['src' => $src, 'webp' => null, 'srcset' => null, 'width' => null, 'height' => null];

        if (str_starts_with($value, 'http')) {
            if (strtolower((string) parse_url($value, PHP_URL_HOST)) === self::UNSPLASH_HOST) $result['srcset'] = self::unsplashSrcset($value);

            return $result;
        }

        $entry = str_starts_with($value, '/images/') ? self::staticEntry($value) : (str_starts_with($value, '/') ? null : self::mediaEntry($value));
        if (! $entry) return $result;
        $result['width'] = $entry['width'] ?? null;
        $result['height'] = $entry['height'] ?? null;
        if (($entry['status'] ?? null) !== ImageOptimizer::STATUS_OPTIMIZED) return $result;

        $candidates = [];
        foreach ($entry['variants'] ?? [] as $variant) {
            if (is_file($entry['dir_path'].'/'.$variant['file'])) $candidates[] = $entry['dir_url'].'/'.rawurlencode($variant['file']).' '.$variant['width'].'w';
        }
        $result['webp'] = $candidates ? implode(', ', $candidates) : null;

        return $result;
    }

    /** Responsive widths for an Unsplash URL, up to the width the editor chose; the crop and other parameters are kept. */
    public static function unsplashSrcset(string $url): ?string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $width = (int) ($query['w'] ?? 0);
        if ($width <= 0) return null;

        $widths = array_values(array_unique([...array_filter(config('kelika.images.widths'), fn ($w) => $w < $width), $width]));
        sort($widths);
        $base = strtok($url, '?');

        return implode(', ', array_map(function (int $w) use ($query, $width, $base) {
            $sized = $query;
            $sized['w'] = $w;
            if (isset($query['h'])) $sized['h'] = max(1, (int) round((int) $query['h'] * $w / $width)); // keep the crop's shape

            return $base.'?'.http_build_query($sized).' '.$w.'w';
        }, $widths));
    }

    /** Directory, relative to public/, holding the copies of a file under public/images. */
    public static function staticVariantsDir(string $publicPath): string
    {
        $relative = substr($publicPath, strlen('/images/'));

        return self::STATIC_VARIANTS_DIR.'/'.preg_replace('/\.[^.\/]+$/', '', $relative);
    }

    public static function staticManifestPath(): string
    {
        return public_path(self::STATIC_VARIANTS_DIR.'/manifest.json');
    }

    /** Media library optimisation details for a stored path, looked up once per request. */
    private static function mediaAsset(string $path): ?MediaAsset
    {
        return self::requestCache('media', fn () => MediaAsset::query()->whereNotNull('metadata')->get(['id', 'disk', 'path', 'metadata'])->keyBy('path'))->get($path);
    }

    private static function mediaEntry(string $path): ?array
    {
        $asset = self::mediaAsset($path);
        $optimization = $asset?->metadata['optimization'] ?? null;
        if (! is_array($optimization)) return null;
        $dir = $asset->variantsDirectory();

        return $optimization + ['dir_path' => Storage::disk($asset->disk)->path($dir), 'dir_url' => Storage::disk($asset->disk)->url($dir)];
    }

    private static function staticEntry(string $publicPath): ?array
    {
        $manifest = self::requestCache('static', function () {
            $file = self::staticManifestPath();
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

            return is_array($data['images'] ?? null) ? $data['images'] : [];
        });
        $entry = $manifest[$publicPath] ?? null;
        if (! is_array($entry)) return null;
        $dir = self::staticVariantsDir($publicPath);

        return $entry + ['dir_path' => public_path($dir), 'dir_url' => '/'.$dir];
    }

    private static function requestCache(string $key, callable $load): mixed
    {
        $attributes = request()->attributes;
        $name = self::class.'.'.$key;
        if (! $attributes->has($name)) $attributes->set($name, $load());

        return $attributes->get($name);
    }
}

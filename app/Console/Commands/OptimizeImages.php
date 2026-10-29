<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Support\ImageOptimizer;
use App\Support\Media;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Creates (or refreshes) WebP copies for the site's own images: files under public/images and images in the media
 * library. A dry run by default: it encodes into a temporary folder to report real sizes, then removes it.
 * Originals are only ever read.
 */
class OptimizeImages extends Command
{
    protected $signature = 'kelika:images
        {--write : Generate and keep the copies (without this, nothing is written)}
        {--force : Regenerate copies even when they are up to date}
        {--only= : Limit to "static" (public/images) or "media" (media library)}';

    protected $description = 'Report or generate optimised WebP copies of the website\'s images (dry run unless --write)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('This command does not run in production. Run it on the development site and deploy the result deliberately.');

            return self::FAILURE;
        }
        $write = (bool) $this->option('write');
        $only = $this->option('only');
        if ($only !== null && ! in_array($only, ['static', 'media'], true)) {
            $this->error('--only must be "static" or "media".');

            return self::INVALID;
        }

        $this->info($write ? 'Generating copies (originals are not modified).' : 'Dry run: nothing in the project is changed. Add --write to generate copies.');
        $rows = [];
        if ($only !== 'media') $rows = [...$rows, ...$this->staticImages($write)];
        if ($only !== 'static') $rows = [...$rows, ...$this->mediaImages($write)];

        $this->table(['Image', 'Format', 'Pixels', 'Original', 'Used on site', 'WebP copies (width: size)', 'Action'], array_map(fn ($r) => [
            $r['path'], $r['format'], $r['pixels'], self::kb($r['bytes']), $r['referenced'], $r['copies'], $r['action'],
        ], $rows));

        return collect($rows)->contains(fn ($r) => str_starts_with($r['action'], 'FAILED')) ? self::FAILURE : self::SUCCESS;
    }

    private function staticImages(bool $write): array
    {
        $manifestFile = Media::staticManifestPath();
        $manifest = is_file($manifestFile) ? (json_decode((string) file_get_contents($manifestFile), true) ?: []) : [];
        $entries = $manifest['images'] ?? [];
        $rows = [];

        $files = array_filter((new Filesystem)->allFiles(public_path('images')), fn ($f) => preg_match('/\.(jpe?g|png|webp)$/i', $f->getFilename())
            && ! str_starts_with(str_replace('\\', '/', $f->getRelativePathname()), 'optimized/'));
        foreach ($files as $file) {
            $publicPath = '/images/'.str_replace('\\', '/', $file->getRelativePathname());
            $source = $file->getPathname();
            $entry = $entries[$publicPath] ?? null;
            $upToDate = $this->isUpToDate($entry, $source, public_path(Media::staticVariantsDir($publicPath)));
            $target = $write ? public_path(Media::staticVariantsDir($publicPath)) : $this->scratchDir();

            if ($upToDate && ! $this->option('force')) {
                $rows[] = $this->row($publicPath, $source, $entry, 'up to date');
                continue;
            }
            $result = ImageOptimizer::process($source, $target);
            if ($write) $entries[$publicPath] = $result;
            else (new Filesystem)->deleteDirectory($target);
            $rows[] = $this->row($publicPath, $source, $result, $this->action($result, $write));
        }

        if ($write) {
            ksort($entries);
            $dir = dirname($manifestFile);
            if (! is_dir($dir)) mkdir($dir, 0755, true);
            $temp = $manifestFile.'.tmp-'.bin2hex(random_bytes(4));
            file_put_contents($temp, json_encode(['version' => config('kelika.images.version'), 'generated_at' => now()->toIso8601String(), 'images' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            rename($temp, $manifestFile);
        }

        return $rows;
    }

    private function mediaImages(bool $write): array
    {
        $rows = [];
        foreach (MediaAsset::query()->where('mime_type', 'like', 'image/%')->orderBy('id')->get() as $asset) {
            $disk = Storage::disk($asset->disk);
            $source = $disk->path($asset->path);
            $entry = $asset->metadata['optimization'] ?? null;
            if (! is_file($source)) {
                $rows[] = ['path' => $asset->path, 'format' => $asset->mime_type, 'pixels' => '-', 'bytes' => 0, 'referenced' => $this->referenced(basename($asset->path)), 'copies' => '-', 'action' => 'skipped: original file is missing'];
                continue;
            }
            if (! $asset->hasVariantsDirectory()) {
                $rows[] = $this->row($asset->path, $source, $entry, 'skipped: unexpected storage path');
                continue;
            }
            if ($this->isUpToDate($entry, $source, $disk->path($asset->variantsDirectory())) && ! $this->option('force')) {
                $rows[] = $this->row($asset->path, $source, $entry, 'up to date');
                continue;
            }

            if ($write) {
                $asset->metadata = $asset->optimizedMetadata();
                $asset->save();
                $this->giveToStorageOwner($disk->path($asset->variantsDirectory()), $disk->path(''));
                $result = $asset->metadata['optimization'];
            } else {
                $result = ImageOptimizer::process($source, $target = $this->scratchDir());
                (new Filesystem)->deleteDirectory($target);
            }
            $rows[] = $this->row($asset->path, $source, $result, $this->action($result, $write));
        }

        return $rows;
    }

    private function isUpToDate(?array $entry, string $source, string $dir): bool
    {
        if (! is_array($entry) || ($entry['status'] ?? null) !== ImageOptimizer::STATUS_OPTIMIZED) return false;
        if ((int) ($entry['version'] ?? 0) !== (int) config('kelika.images.version') || ($entry['source_sha1'] ?? null) !== sha1_file($source)) return false;
        foreach ($entry['variants'] ?? [] as $variant) if (! is_file($dir.'/'.$variant['file'])) return false;

        return true;
    }

    private function row(string $path, string $source, ?array $result, string $action): array
    {
        $info = @getimagesize($source);

        return [
            'path' => $path, 'format' => $info['mime'] ?? '?', 'pixels' => $info ? $info[0].'×'.$info[1] : '?', 'bytes' => (int) @filesize($source),
            'referenced' => $this->referenced(basename($path)),
            'copies' => $result && ! empty($result['variants']) ? implode(', ', array_map(fn ($v) => $v['width'].': '.self::kb($v['bytes']), $result['variants'])) : '-',
            'action' => $action,
        ];
    }

    private function action(array $result, bool $write): string
    {
        return match ($result['status']) {
            ImageOptimizer::STATUS_OPTIMIZED => ($write ? 'generated ' : 'would generate ').count($result['variants']).' copies',
            ImageOptimizer::STATUS_TOO_LARGE => 'not optimised: too large (original kept in use)',
            ImageOptimizer::STATUS_UNSUPPORTED => 'not optimised: unsupported file',
            default => 'FAILED: '.$result['error'],
        };
    }

    /** Whether any page section, content item, setting, menu link or redirect mentions this file name. */
    private function referenced(string $name): string
    {
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $name).'%';
        foreach (['page_sections' => 'data', 'content_items' => 'data', 'settings' => 'value', 'menu_items' => 'url', 'redirects' => 'to_path'] as $table => $column) {
            if (DB::table($table)->whereRaw(DB::getQueryGrammar()->wrap($column)." LIKE ? ESCAPE '!'", [$like])->exists()) return 'yes';
        }

        return 'no';
    }

    private function scratchDir(): string
    {
        return sys_get_temp_dir().'/kelika-images-dry-run-'.bin2hex(random_bytes(6));
    }

    /**
     * Hand new media copies to the owner of the storage folder (the web server), so the CMS can later replace them.
     * Only root can change ownership; for anyone else these calls do nothing.
     */
    private function giveToStorageOwner(string $dir, string $root): void
    {
        if (! is_dir($dir)) return;
        $owner = fileowner($root);
        $group = filegroup($root);
        foreach ([$dir, ...glob($dir.'/*') ?: []] as $path) {
            if (fileowner($path) !== $owner) @chown($path, $owner);
            if (filegroup($path) !== $group) @chgrp($path, $group);
        }
    }

    private static function kb(int $bytes): string
    {
        return number_format($bytes / 1024, 1).' KB';
    }
}

<?php

namespace App\Support;

use GdImage;
use RuntimeException;
use Throwable;

/**
 * Generates WebP copies of an image at several widths, using GD. The source file is only ever read.
 *
 * Copies are written into one directory per image, named "w{width}-{key}.webp", where the key is derived from the
 * source's content and the optimisation settings. Re-processing the same source with the same settings therefore
 * rewrites the same files instead of adding new ones, and a changed source or setting produces new names (so
 * browsers never keep a stale copy). Each copy is written to a temporary file, checked by decoding it, and only
 * then renamed into place. If anything fails, every file created by this run is removed again.
 *
 * The result describes the outcome; only "optimized" means usable copies exist.
 */
class ImageOptimizer
{
    public const STATUS_OPTIMIZED = 'optimized';
    public const STATUS_TOO_LARGE = 'too_large';
    public const STATUS_UNSUPPORTED = 'unsupported';
    public const STATUS_FAILED = 'failed';

    private const READABLE = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'];

    /**
     * @return array{status: string, version: int, source_sha1: ?string, width: ?int, height: ?int,
     *               variants: list<array{width: int, height: int, file: string, bytes: int}>, error: ?string, processed_at: string}
     */
    public static function process(string $source, string $outputDir, bool $removeStale = true): array
    {
        $settings = config('kelika.images');
        $result = ['status' => self::STATUS_FAILED, 'version' => (int) $settings['version'], 'source_sha1' => null, 'width' => null,
            'height' => null, 'variants' => [], 'error' => null, 'processed_at' => now()->toIso8601String()];
        $created = [];
        $image = null;

        try {
            $info = @getimagesize($source);
            $sha1 = @sha1_file($source);
            if ($info === false || $sha1 === false || ! isset(self::READABLE[$info[2]])) {
                return ['status' => self::STATUS_UNSUPPORTED, 'error' => 'Not a JPEG, PNG or WebP image.'] + $result;
            }
            $result['source_sha1'] = $sha1;
            $orientation = $info[2] === IMAGETYPE_JPEG ? (int) (@exif_read_data($source)['Orientation'] ?? 1) : 1;
            [$width, $height] = $orientation >= 5 && $orientation <= 8 ? [$info[1], $info[0]] : [$info[0], $info[1]];
            $result['width'] = $width;
            $result['height'] = $height;

            if ($width * $height > (int) $settings['max_pixels']) {
                return ['status' => self::STATUS_TOO_LARGE, 'error' => sprintf('%d × %d pixels is above the %s-megapixel processing limit.', $width, $height, $settings['max_pixels'] / 1_000_000)] + $result;
            }

            $image = self::decode($source, $info[2], $orientation);
            $isGraphic = $info[2] === IMAGETYPE_PNG;
            $quality = (int) $settings['quality'][$isGraphic ? 'graphic' : 'photo'];
            $key = substr(sha1($sha1.'|'.$settings['version'].'|'.$quality), 0, 12);
            $maxWidth = max($settings['widths']);
            $targets = array_values(array_unique([...array_filter($settings['widths'], fn ($w) => $w < $width), min($width, $maxWidth)]));
            sort($targets);

            if (! is_dir($outputDir) && ! @mkdir($outputDir, 0755, true) && ! is_dir($outputDir)) {
                throw new RuntimeException('Could not create the directory for optimised copies.');
            }

            foreach ($targets as $targetWidth) {
                $targetHeight = max(1, (int) round($height * $targetWidth / $width));
                $file = sprintf('w%d-%s.webp', $targetWidth, $key);
                $final = $outputDir.'/'.$file;
                $temp = $outputDir.'/.tmp-'.bin2hex(random_bytes(6)).'.webp';
                $created[] = $temp;

                $scaled = $targetWidth === $width ? $image : self::resize($image, $targetWidth, $targetHeight);
                $written = imagewebp($scaled, $temp, $quality);
                if ($scaled !== $image) imagedestroy($scaled);
                $check = $written ? @getimagesize($temp) : false;
                if (! $check || $check[2] !== IMAGETYPE_WEBP || $check[0] !== $targetWidth || $check[1] !== $targetHeight) {
                    throw new RuntimeException("The {$targetWidth}px WebP copy could not be written or did not verify.");
                }

                clearstatcache(true, $temp);
                $bytes = filesize($temp);
                // A same-size copy is only worth serving when it is meaningfully smaller than the original file.
                if ($targetWidth === $width && $bytes > (1 - (float) $settings['min_saving']) * filesize($source)) {
                    @unlink($temp);
                    continue;
                }

                if (! is_file($final)) $created[] = $final;
                if (! @rename($temp, $final)) throw new RuntimeException("The {$targetWidth}px WebP copy could not be moved into place.");
                @chmod($final, 0644);
                $result['variants'][] = ['width' => $targetWidth, 'height' => $targetHeight, 'file' => $file, 'bytes' => $bytes];
            }

            // Copies from earlier settings are regenerable and no longer referenced; this directory holds only copies.
            // A replacement passes false and moves the previous copies to history itself.
            if ($removeStale) {
                $keep = array_column($result['variants'], 'file');
                foreach (glob($outputDir.'/w*-*.webp') ?: [] as $old) {
                    if (! in_array(basename($old), $keep, true)) @unlink($old);
                }
            }

            $result['status'] = self::STATUS_OPTIMIZED;

            return $result;
        } catch (Throwable $exception) {
            foreach ($created as $path) if (is_file($path)) @unlink($path);

            return ['status' => self::STATUS_FAILED, 'variants' => [], 'error' => $exception->getMessage()] + $result;
        } finally {
            if ($image instanceof GdImage) imagedestroy($image);
            foreach (glob($outputDir.'/.tmp-*.webp') ?: [] as $leftover) @unlink($leftover);
        }
    }

    private static function decode(string $source, int $type, int $orientation): GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
        };
        if (! $image instanceof GdImage) throw new RuntimeException('The image could not be decoded.');

        if (! imageistruecolor($image)) imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        // Browsers display JPEGs according to their EXIF orientation, so the copies must match that.
        $rotated = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };
        if ($rotated !== $image) { imagedestroy($image); $image = $rotated; }
        if (in_array($orientation, [2, 4, 5, 7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);

        return $image;
    }

    private static function resize(GdImage $image, int $width, int $height): GdImage
    {
        $scaled = imagecreatetruecolor($width, $height);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $scaled;
    }
}

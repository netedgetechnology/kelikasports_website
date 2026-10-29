<?php

namespace App\Support;

/**
 * The public website's logos, from the global settings (separate from the CMS logo in AdminBranding):
 * - logo_url: the website logo, used in the header;
 * - mobile_logo_url: optional phone-sized header logo (empty = the website logo);
 * - footer_logo_url: optional footer logo (empty = the website logo).
 * A logo is only used while its file can be served: a media library image that is not deleted and still on disk, a
 * file under public/, or an absolute http(s) URL. Otherwise the next fallback applies, ending with the site name.
 */
class PublicLogos
{
    /** @return array{header: ?string, mobile: ?string, footer: ?string} */
    public static function resolve(array|\ArrayAccess $settings): array
    {
        $attributes = request()->attributes;
        $values = array_map(fn ($key) => trim((string) ($settings[$key] ?? '')), ['logo_url' => 'logo_url', 'mobile_logo_url' => 'mobile_logo_url', 'footer_logo_url' => 'footer_logo_url']);
        $memo = self::class.'.'.md5(json_encode($values));
        if ($attributes->has($memo)) return $attributes->get($memo);

        // One query for any media library paths among them.
        $media = MediaPicker::selectablePaths(array_values(array_filter($values, fn ($v) => str_starts_with($v, 'media/'))));
        $available = fn (string $value) => self::available($value, $media) ? $value : null;
        $header = $available($values['logo_url']);
        $result = ['header' => $header, 'mobile' => $header ? $available($values['mobile_logo_url']) : null, 'footer' => $available($values['footer_logo_url']) ?? $header];
        $attributes->set($memo, $result);

        return $result;
    }

    /** @param list<string> $availableMedia */
    private static function available(string $value, array $availableMedia): bool
    {
        if ($value === '' || ! SafeUrl::isSafe($value, true)) return false;
        if (preg_match('#^https?://#i', $value)) return true;
        if (str_starts_with($value, 'media/')) return in_array($value, $availableMedia, true);
        if (! str_starts_with($value, '/')) return false;

        $public = realpath(public_path());
        $file = realpath(public_path(ltrim(parse_url($value, PHP_URL_PATH) ?? '', '/')));

        return $file !== false && $public !== false && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file);
    }
}

<?php

namespace App\Support;

class SafeUrl
{
    public static function isGoogleMapsEmbed(?string $value): bool
    {
        if (! $value || ! filter_var($value, FILTER_VALIDATE_URL)) return false;

        $parts = parse_url($value);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        return ($host === 'www.google.com' && str_starts_with($path, '/maps/embed'))
            || ($host === 'maps.google.com' && str_starts_with($path, '/maps'));
    }

    public static function isSafe(?string $value, bool $allowImagePath = false): bool
    {
        if ($value === null || trim($value) === '') return true;

        $value = trim($value);
        if (preg_match('/[\x00-\x1F\\\\]/', $value)) return false;
        if (str_starts_with($value, '#') || str_starts_with($value, '?')) return true;
        if (preg_match('/^\/(?!\/)/', $value)) return true;
        if (preg_match('/^https?:\/\//i', $value)) return true;
        if (! $allowImagePath && preg_match('/^(mailto:|tel:)/i', $value)) return true;
        if ($allowImagePath && preg_match('/^media\/[a-zA-Z0-9_\/.\-]+$/', $value) && ! str_contains($value, '..')) return true;

        return false;
    }
}

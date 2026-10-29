<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Site-wide branding settings (fonts, button shape, colours) and the values used when a setting is missing, empty
 * or invalid. The defaults are the brand values the CMS is seeded with.
 */
class Branding
{
    public const FONTS = ['Inter', 'Arial', 'system-ui', 'Georgia'];

    public const BUTTON_STYLES = ['square', 'soft', 'rounded'];

    public const DEFAULTS = [
        'font_family' => 'Inter', 'button_style' => 'square',
        'primary_color' => '#064f43', 'terracotta_color' => '#cf6d50', 'gold_color' => '#d4a35d',
        'earth_color' => '#176b4e', 'water_color' => '#1687c9', 'fire_color' => '#d94737',
        'air_color' => '#38a9ce', 'space_color' => '#8c45df',
    ];

    /** Replaces every missing, empty or invalid branding value with its default; valid configured values are kept. */
    public static function apply(Collection $settings): Collection
    {
        foreach (self::DEFAULTS as $key => $default) {
            if (! self::isValid($key, $settings->get($key))) $settings->put($key, $default);
        }

        return $settings;
    }

    private static function isValid(string $key, mixed $value): bool
    {
        if (! is_string($value)) return false;

        return match ($key) {
            'font_family' => in_array($value, self::FONTS, true),
            'button_style' => in_array($value, self::BUTTON_STYLES, true),
            default => (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $value),
        };
    }
}

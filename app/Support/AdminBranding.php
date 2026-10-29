<?php

namespace App\Support;

use App\Models\Setting;

/**
 * CMS (admin panel) branding: an optional logo for the sidebar and the sidebar's background colour, both kept in the
 * global settings. Anything missing or invalid falls back to the original look (the KELIKA wordmark text on dark
 * green), so a bad value can never break or hide the CMS navigation.
 */
class AdminBranding
{
    public const DEFAULT_SIDEBAR_COLOR = '#073f36';

    /** @return array{logo: ?string, sidebar: array<string, string>} */
    public static function current(): array
    {
        $attributes = request()->attributes;
        if (! $attributes->has(self::class)) {
            $settings = Setting::query()->whereIn('key', ['admin_logo_url', 'admin_sidebar_color'])->pluck('value', 'key');
            $attributes->set(self::class, ['logo' => self::logoUrl($settings['admin_logo_url'] ?? null), 'sidebar' => self::sidebar($settings['admin_sidebar_color'] ?? null)]);
        }

        return $attributes->get(self::class);
    }

    /** Public URL of the logo, only while it is an available library image (not deleted, file present). */
    public static function logoUrl(mixed $path): ?string
    {
        if (! is_string($path) || ! str_starts_with($path, 'media/')) return null;

        return MediaPicker::selectablePaths([$path]) ? Media::url($path) : null;
    }

    public static function isValidColor(mixed $color): bool
    {
        return is_string($color) && (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $color);
    }

    /**
     * CSS custom properties for the sidebar. The text colour is whichever of white or black contrasts more with the
     * background, so links, headings and the sign-out button stay readable (at least 4.5:1) on any colour.
     *
     * @return array<string, string>
     */
    public static function sidebar(mixed $color): array
    {
        $background = self::isValidColor($color) ? strtolower($color) : self::DEFAULT_SIDEBAR_COLOR;
        $light = self::contrast($background, '#ffffff') >= self::contrast($background, '#000000');
        $ink = $light ? '255,255,255' : '0,0,0';

        return [
            '--admin-sidebar-bg' => $background,
            '--admin-sidebar-fg' => $light ? '#ffffff' : '#000000',
            '--admin-sidebar-muted' => "rgba({$ink},.72)",
            '--admin-sidebar-hover' => "rgba({$ink},.12)",
            '--admin-sidebar-active' => "rgba({$ink},.2)",
            '--admin-sidebar-border' => "rgba({$ink},.55)",
        ];
    }

    /** Inline style declaring the sidebar properties; every value is generated here from a validated colour. */
    public static function sidebarStyle(): string
    {
        return collect(self::current()['sidebar'])->map(fn ($value, $name) => "{$name}:{$value}")->implode(';');
    }

    /** WCAG contrast ratio between two #rrggbb colours. */
    public static function contrast(string $a, string $b): float
    {
        [$la, $lb] = [self::luminance($a), self::luminance($b)];

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $channel = function (string $pair) {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel(substr($hex, 1, 2)) + 0.7152 * $channel(substr($hex, 3, 2)) + 0.0722 * $channel(substr($hex, 5, 2));
    }
}

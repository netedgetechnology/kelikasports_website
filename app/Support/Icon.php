<?php

namespace App\Support;

/**
 * Inline SVG line icons for public templates. CMS "icon" fields accept an icon name (e.g. "trophy"),
 * one of the legacy glyphs used by the starter content, or any short text which is rendered as-is.
 */
class Icon
{
    private const PATHS = [
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'play' => '<path d="M8 5.5v13l11-6.5z" fill="currentColor" stroke="none"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.4-4.4"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'phone' => '<path d="M6.6 3h2.6l1.4 4.2-2 1.4a12 12 0 0 0 5.8 5.8l1.4-2 4.2 1.4v2.6a2 2 0 0 1-2.2 2A15 15 0 0 1 4.6 5.2 2 2 0 0 1 6.6 3z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'quote' => '<path fill="currentColor" stroke="none" d="M4 18v-5c0-4 2-6.5 5.5-7.5l.8 1.6C8.3 8 7.5 9.4 7.4 11H10v7zm10 0v-5c0-4 2-6.5 5.5-7.5l.8 1.6c-2 .9-2.8 2.3-2.9 3.9H20v7z"/>',
        'coach' => '<circle cx="12" cy="9.5" r="3.5"/><path d="M5 21a7 7 0 0 1 14 0"/><path d="M6.5 9.5a5.5 5.5 0 0 1 11 0"/><rect x="5" y="8.5" width="2.4" height="4" rx="1"/><rect x="16.6" y="8.5" width="2.4" height="4" rx="1"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.8"/><path d="M15.5 14.2a5.5 5.5 0 0 1 6 5.8"/>',
        'kids' => '<circle cx="12" cy="6.5" r="2.5"/><circle cx="5.5" cy="9" r="2"/><circle cx="18.5" cy="9" r="2"/><path d="M8 20v-3a4 4 0 0 1 8 0v3M2 20v-2a3.5 3.5 0 0 1 5-3.2M22 20v-2a3.5 3.5 0 0 0-5-3.2"/>',
        'chart' => '<path d="M4 20h16M6 17v-3M10 17v-5M14 17v-4M18 17V9"/><path d="m5 10 5-4 4 3 6-5M16 4h4v4"/>',
        'trophy' => '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8.5 20h7M9.5 17h5"/>',
        'leaf' => '<path d="M5 19c0-9 6-14 15-14 0 9-5 15-14 15"/><path d="m5 19 9-9"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9z"/>',
        'heart' => '<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z"/>',
        'target' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r=".8"/>',
        'grid' => '<rect x="4" y="4" width="7" height="7"/><rect x="13" y="4" width="7" height="7"/><rect x="4" y="13" width="7" height="7"/><rect x="13" y="13" width="7" height="7"/>',
        'check' => '<path d="m5 12 4.5 4.5L19 7"/>',
        'court' => '<rect x="3" y="4" width="18" height="16" rx="1"/><path d="M3 12h18M7.5 4v16M16.5 4v16M3 7.5h4.5M16.5 7.5H21M3 16.5h4.5M16.5 16.5H21"/>',
        'sofa' => '<path d="M5 11V8a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v3"/><path d="M3 13a2 2 0 0 1 4 0v1h10v-1a2 2 0 0 1 4 0v4H3zM5 17v2M19 17v2"/>',
        'bag' => '<path d="M5 8h14l-1 12H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'hanger' => '<path d="M10 5a2 2 0 1 1 2 2v1.5L3 15a1.5 1.5 0 0 0 1 2.5h16a1.5 1.5 0 0 0 1-2.5l-9-6.5"/>',
        'car' => '<path d="M5 16v-5l2-5h10l2 5v5M3 16h18v2H3zM5 11h14"/><circle cx="7.5" cy="13.5" r=".8"/><circle cx="16.5" cy="13.5" r=".8"/>',
        'shuttle' => '<circle cx="7" cy="17" r="3"/><path d="m9 15 9-11M9.5 15.5 19 7M8.5 14.5 13 3"/>',
        'handshake' => '<path d="m2.5 11.5 4-4 4 1.5 2-1.5 3 .5 6 3.5-3 3"/><path d="m6.5 7.5 4.5 5.5a1.6 1.6 0 0 0 2.4-2.1L12 9.5M9 14.5l1.5 1.5a1.4 1.4 0 0 0 2-2M11.5 17l.8.8a1.4 1.4 0 0 0 2-2L13 14.5M14.3 15.8l.7.7a1.4 1.4 0 0 0 2-2l-2.5-2.5M2.5 11.5l2.5 2.5"/>',
        'chat' => '<path d="M4 5h11a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H9l-4 3v-3H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"/><path d="M17 9h3a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-1v2.5L16 18h-3.5a2 2 0 0 1-1.6-.8M6 9h7M6 12h4"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'eye' => '<path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'gem' => '<path d="M7 4h10l4 5.5L12 21 3 9.5z"/><path d="M3 9.5h18M9.5 4 8 9.5 12 21M14.5 4 16 9.5 12 21"/>',
        'lotus' => '<path d="M12 19c-2.5-2.2-3.5-5-3.5-7.5S10 6.5 12 4.5c2 2 3.5 4.5 3.5 7S14.5 16.8 12 19z"/><path d="M12 19c-3.8.3-7-1.6-8.5-5.5 2.4-.6 4.3-.4 5.9.4M12 19c3.8.3 7-1.6 8.5-5.5-2.4-.6-4.3-.4-5.9.4M4.5 9.5C3 10 2.2 10.5 1.8 11.2M19.5 9.5c1.5.5 2.3 1 2.7 1.7"/>',
        'camera' => '<path d="M4 8h3l1.5-2.5h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/>',
        'cap' => '<path d="m2.5 9.5 9.5-4.5 9.5 4.5-9.5 4.5z"/><path d="M6.5 11.6v4.4c0 1.4 2.5 3 5.5 3s5.5-1.6 5.5-3v-4.4M21.5 9.5v5"/>',
        'shield' => '<path d="M12 3 5 6v5.5c0 4.4 3 8 7 9.5 4-1.5 7-5.1 7-9.5V6z"/><path d="m8.8 12 2.2 2.2 4.2-4.4"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M10 21v-3h4v3"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 21a7 7 0 0 1 14 0"/>',
        'shoe' => '<path d="M3 15.5V9.5l3.5 1 2-3.5 3 3.5c2 1.6 4.4 2.3 7 2.6a2.5 2.5 0 0 1 2.5 2.4v1.5H3z"/><path d="M3 18.5h18M10 10l1.6-1.4M12.2 12l1.6-1.4"/>',
        'dumbbell' =>'<path d="M6.5 7v10M17.5 7v10M3.5 9.5v5M20.5 9.5v5M6.5 12h11"/>',
        'brain' => '<path d="M12 5.5a3 3 0 0 0-5.6-1.3A3 3 0 0 0 4 8.5a3 3 0 0 0 .3 5A3.2 3.2 0 0 0 8 18.8a2.8 2.8 0 0 0 4 .7zM12 5.5a3 3 0 0 1 5.6-1.3A3 3 0 0 1 20 8.5a3 3 0 0 1-.3 5 3.2 3.2 0 0 1-3.7 5.3 2.8 2.8 0 0 1-4 .7z"/><path d="M12 5.5v14M8.5 9.5c1 0 2 .6 2 2M15.5 9.5c-1 0-2 .6-2 2M8 14.5h2M16 14.5h-2"/>',
        'mountain' => '<path d="M2.5 19.5 9 8l3.5 6 2.5-3.5 6.5 9z"/><path d="m7.2 11.2 1.8 1.3 1.6-1.2"/>',
        'waves' => '<path d="M3 8c1.5-1.5 3-1.5 4.5 0s3 1.5 4.5 0 3-1.5 4.5 0 3 1.5 4.5 0M3 12.5c1.5-1.5 3-1.5 4.5 0s3 1.5 4.5 0 3-1.5 4.5 0 3 1.5 4.5 0M3 17c1.5-1.5 3-1.5 4.5 0s3 1.5 4.5 0 3-1.5 4.5 0 3 1.5 4.5 0"/>',
        'flame' => '<path d="M12 21c-3.9 0-7-2.8-7-6.6 0-3.2 2-5.2 3.6-7.1.4 1.8 1.3 2.9 2.4 3.4C11 6.9 12.6 4.6 14.6 3c.3 3 1.9 4.6 3.1 6.4a8 8 0 0 1 1.3 5c0 3.8-3.1 6.6-7 6.6z"/><path d="M12 21c-1.7 0-3-1.3-3-3 0-1.9 1.6-2.8 2.4-4.6 1.1 1 3.6 2.6 3.6 4.6 0 1.7-1.3 3-3 3z"/>',
        'wind' => '<path d="M3 8.5h11a2.5 2.5 0 1 0-2.5-2.5M3 12.5h15a2.5 2.5 0 1 1-2.5 2.5M3 16.5h8"/>',
        'football' => '<circle cx="12" cy="12" r="9"/><path d="m12 7.5 4 2.9-1.5 4.6h-5L8 10.4z"/><path d="M12 7.5V3.2M16 10.4l4-1.3M14.5 15l2.5 3.5M9.5 15 7 18.5M8 10.4 4 9.1"/>',
        'cricket' => '<path d="m14 4 6 6-9.5 9.5a2 2 0 0 1-2.8 0l-3.2-3.2a2 2 0 0 1 0-2.8z"/><path d="m3 21 2.4-2.4"/><circle cx="18.5" cy="18.5" r="2"/>',
        'orbit' => '<circle cx="12" cy="12" r="3"/><path d="M19.5 7.5A9 9 0 1 0 21 12"/><circle cx="19.5" cy="7.5" r="1" fill="currentColor"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r=".6" fill="currentColor"/>',
        'facebook' => '<path d="M14 21v-7h3l.5-3.5H14V8.5c0-1 .4-1.6 1.7-1.6h1.9V3.8A24 24 0 0 0 15 3.6c-2.6 0-4.3 1.6-4.3 4.4v2.5H8V14h2.7v7"/>',
        'youtube' => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10 9 5 3-5 3z" fill="currentColor"/>',
        'linkedin' => '<rect x="3.5" y="3.5" width="17" height="17" rx="2"/><path d="M8 10v6M8 7.5v.01M12 16v-6M12 13a2.5 2.5 0 0 1 5 0v3"/>',
        'x' => '<path fill="currentColor" stroke="none" d="M17.75 3h3.07l-6.7 7.66L22 21h-6.17l-4.83-6.32L5.47 21H2.4l7.17-8.2L2 3h6.33l4.37 5.78L17.75 3Zm-1.08 16.2h1.7L7.4 4.73H5.58l11.09 14.47Z"/>',
    ];

    /** Glyphs used by the seeded starter content, mapped to equivalent line icons. */
    private const GLYPHS = [
        '✦' => 'coach', '↗' => 'chart', '❋' => 'leaf', '♧' => 'users', '♜' => 'trophy', '☆' => 'star',
        '★' => 'star', '◎' => 'target', '▦' => 'grid', '✓' => 'check', '♥' => 'heart', '⌖' => 'pin',
    ];

    /** Fallbacks inferred from a card title when no icon has been chosen in the CMS. */
    private const KEYWORDS = [
        'kid' => 'kids', 'junior' => 'kids', 'child' => 'kids', 'youth' => 'chart', 'development' => 'chart',
        'performance' => 'trophy', 'competitive' => 'trophy', 'tournament' => 'trophy', 'adult' => 'coach',
        'coach' => 'coach', 'court' => 'court', 'lounge' => 'sofa', 'shop' => 'bag', 'store' => 'bag',
        'changing' => 'hanger', 'locker' => 'hanger', 'parking' => 'car', 'community' => 'users',
        'holistic' => 'leaf', 'personal' => 'chart', 'life skill' => 'star',
    ];

    public static function names(): array
    {
        return array_keys(self::PATHS);
    }

    public static function resolve(?string $icon, ?string $title = null): ?string
    {
        $icon = trim((string) $icon);
        if ($icon !== '') {
            $key = strtolower($icon);
            if (isset(self::PATHS[$key])) return $key;

            return self::GLYPHS[$icon] ?? null;
        }

        $title = strtolower((string) $title);
        foreach (self::KEYWORDS as $keyword => $name) {
            if ($title !== '' && str_contains($title, $keyword)) return $name;
        }

        return null;
    }

    public static function svg(string $name, string $class = 'kl-icon', int $size = 24): string
    {
        $paths = self::PATHS[$name] ?? self::PATHS['check'];

        return '<svg class="'.e($class).'" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$paths.'</svg>';
    }

    /**
     * Render a CMS icon value: a known icon as SVG, otherwise the escaped text, otherwise a title-based fallback.
     */
    public static function render(?string $icon, ?string $title = null, string $class = 'kl-icon', int $size = 24): string
    {
        $name = self::resolve($icon, $title);
        if ($name) return self::svg($name, $class, $size);

        $icon = trim((string) $icon);

        return $icon !== '' ? '<span class="'.e($class).' kl-icon-text" aria-hidden="true">'.e($icon).'</span>' : '';
    }
}

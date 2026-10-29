<?php

namespace App\Support;

use App\Models\PageSection;

/**
 * Design defaults for optional section fields. A default applies only while the field has never been saved,
 * so anything an editor stores in the CMS (including an empty value) always wins.
 */
class SectionDefaults
{
    public const HEADING_ACCENTS = ['none', 'terracotta', 'gold', 'green'];

    /**
     * Hero image overlay darkness (cinematic layout), in percent. 53 is the design as built: the average opacity of its
     * dark-green overlay across the photo on desktop (left-to-right fade 0.456 combined with bottom fade 0.139). Other
     * values scale every stop of the same gradients by value / 53; 0 removes the overlay.
     */
    public const OVERLAY_DEFAULT = 53;

    public const OVERLAY_MAX = 80;

    public const LAYOUTS = [
        'hero' => ['standard' => 'Image beside the text', 'cinematic' => 'Full-width photo behind the text'],
        'split' => ['image_right' => 'Image on the right', 'image_left' => 'Full-bleed image on the left', 'program' => 'Featured program: copy, photo and a highlights panel (Programs design)', 'courts' => 'Main attraction: copy, wide photo and a row of image cards (Facilities design)', 'story_left' => 'Photo on the left fading into title-case copy (About design)'],
        'event_list' => ['featured' => 'Featured event + cards', 'compact' => 'Intro column + compact cards', 'cards' => 'Heading row above three-column event cards (Events design)', 'showcase' => 'Featured event panel beside an upcoming events list (Events design)'],
        'gallery' => ['grid' => 'Image grid (hidden until images are added)', 'strip' => 'Heading row above one row of five photos (Events design)'],
        // The options marked (Academy design) / (Sports design) / (Programs design) / (Facilities design) / (Events design) / (About design) / (Contact design) below follow those pages' approved references (design-reference/Kelika Sports - Academy Page.png, Kelika Sports - Sports Page.png, Kelika Sports - Structured Programs Page.png, Kelika Sports - Facilities.png, Kelika Sports - Events Page.png, Kelika Sports - About Page.png, Kelika Sports - Contact Page.png).
        'feature_list' => ['columns' => 'Intro beside a grid of features', 'panel' => 'Intro, image and a green features panel (Academy design)', 'row' => 'Heading row above one row of icon features (Academy design)', 'guide' => 'Heading and button beside centred icon features (Sports design)', 'numbered' => 'Heading row above numbered feature cards (Programs design)', 'options' => 'Heading beside option cards with checklists (Programs design)', 'strip' => 'Heading above one row of icon features on a card (Events design)', 'enquiries' => 'Quick enquiry cards beside the FAQ section (Contact design)'],
        'faq' => ['standard' => 'Heading beside the questions', 'beside' => 'Shown beside the quick enquiry cards (Contact design)'],
        'contact_form' => ['standard' => 'Heading beside the form', 'split' => 'Form panel beside the location details (Contact design)'],
        'map_embed' => ['standard' => 'Location and map', 'beside' => 'Shown beside the contact form (Contact design)'],
        'program_list' => ['simple' => 'Compact program cards', 'detailed' => 'Program cards with highlights and a button (Academy design)', 'cards' => 'Photo cards, the first one highlighted (Programs design)'],
        'card_grid' => ['grid' => 'Card grid', 'featured' => 'Large image beside a 2 × 2 card grid (Academy design)', 'teaser' => 'Numbered intro beside a row of image cards (Sports design)', 'pathway' => 'Heading row above image cards joined by arrows (Programs design)', 'spaces' => 'Heading row above image or icon cards (Facilities design)'],
        'five_elements' => ['simple' => 'Icon columns', 'cards' => 'Image cards (Academy design)', 'lines' => 'Heading row above large line icons in coloured columns (About design)'],
        'stats' => ['image' => 'Green band with figures', 'highlights' => 'Light band of short highlights (About design)'],
        'cta' => ['standard' => 'Uppercase heading', 'program' => 'Title-case heading, photo on the right and leaf decorations (Programs, Facilities and Events design)', 'journey' => 'Title-case heading on a green panel with an angled photo on the right (About design)'],
        'facilities_showcase' => ['grid' => 'Facility cards', 'amenities' => 'Photo beside the facilities as icon features (Facilities design)'],
        'sport_directory' => ['editorial' => 'Alternating image and text', 'bands' => 'Full-width bands with a features panel (Sports design)'],
    ];

    /** Homepage design copy from the approved reference, keyed by section type. */
    private const HOME = [
        'hero' => [
            'script_text' => 'More|Than|A Sport',
            'values_text' => 'TRAIN|COMPETE|GROW|BELONG',
            'items' => [
                ['title' => 'Professional|Coaching', 'icon' => 'coach'],
                ['title' => 'World-class|Facilities', 'icon' => 'court'],
                ['title' => 'Programs|for All Ages', 'icon' => 'users'],
                ['title' => 'Tournaments|& Events', 'icon' => 'trophy'],
            ],
        ],
        'feature_list' => ['button_label' => 'Our Philosophy', 'button_url' => '/about'],
        'cta' => ['script_text' => 'Same Court|Bigger|Possibilities'],
    ];

    private const TYPE_ACCENTS = [
        'card_grid' => 'none', 'split' => 'green', 'feature_list' => 'gold', 'event_list' => 'none',
        'testimonials' => 'none', 'cta' => 'none', 'stats' => 'none',
    ];

    public static function apply(PageSection $section): array
    {
        $data = $section->data ?? [];

        if (isset(self::HOME[$section->type]) && $section->page?->slug === 'home') {
            foreach (self::HOME[$section->type] as $key => $value) {
                if (! array_key_exists($key, $data)) $data[$key] = $value;
            }
        }

        return $data;
    }

    public static function accent(PageSection $section): string
    {
        $accent = (string) ($section->data['heading_accent'] ?? '');

        return in_array($accent, self::HEADING_ACCENTS, true) ? $accent : (self::TYPE_ACCENTS[$section->type] ?? 'terracotta');
    }

    public static function layout(PageSection $section): ?string
    {
        $options = self::LAYOUTS[$section->type] ?? null;
        if (! $options) return null;

        $layout = (string) ($section->data['layout'] ?? '');
        if (array_key_exists($layout, $options)) return $layout;

        return match ($section->type) {
            'hero' => 'standard',
            'split' => self::isFacilities($section) ? 'image_left' : 'image_right',
            'event_list' => $section->page?->slug === 'home' ? 'compact' : 'featured',
            default => array_key_first($options),
        };
    }

    /**
     * Two-column Contact layouts pair a host section with a companion section on the same page: the contact form
     * ("split") shows the location section ("beside") in its right column, and the quick enquiry cards ("enquiries")
     * show the FAQ section ("beside"). The companion's own template renders nothing while its host is on the page.
     */
    public const COMPANIONS = ['map_embed' => ['contact_form', 'split'], 'faq' => ['feature_list', 'enquiries']];

    /** The visible companion section a host renders beside itself, if any. */
    public static function companion(PageSection $host, string $type): ?PageSection
    {
        return $host->page?->sections->first(fn ($section) => $section->type === $type && self::layout($section) === 'beside');
    }

    /** Whether a companion ("beside") section is rendered by its host on this page instead of on its own. */
    public static function shownByHost(PageSection $section): bool
    {
        [$hostType, $hostLayout] = self::COMPANIONS[$section->type] ?? [null, null];
        if (! $hostType || self::layout($section) !== 'beside') return false;

        return (bool) $section->page?->sections->contains(fn ($host) => $host->type === $hostType && self::layout($host) === $hostLayout);
    }

    /** The saved overlay darkness (0 to OVERLAY_MAX), or the default when it is missing or invalid. */
    public static function overlayDarkness(PageSection $section): int
    {
        $value = $section->data['overlay_darkness'] ?? null;
        $value = is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;

        return $value !== null && $value >= 0 && $value <= self::OVERLAY_MAX ? $value : self::OVERLAY_DEFAULT;
    }

    public static function isFacilities(PageSection $section): bool
    {
        return str_contains(strtolower((string) ($section->data['eyebrow'] ?? '')), 'facilit');
    }

    /**
     * A heading line split into plain and highlighted parts: words wrapped in *asterisks* are highlighted, e.g.
     * "*YOU*" or "A *STRONGER* YOU". The parts are plain text, for the template to escape.
     *
     * @return list<array{text: string, highlight: bool}>
     */
    public static function highlights(string $line): array
    {
        $parts = [];
        // The marked text must start and end with a non-space character, so a lone "*" or "* x *" is kept as written.
        $marker = '\*(?:[^\s*]|[^\s*][^*]*[^\s*])\*';
        foreach (preg_split("/({$marker})/u", $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
            $highlight = (bool) preg_match("/^{$marker}$/u", $part);
            $parts[] = ['text' => $highlight ? substr($part, 1, -1) : $part, 'highlight' => $highlight];
        }

        return $parts;
    }

    /** Split a pipe-delimited CMS value into trimmed, non-empty lines. */
    public static function lines(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode('|', (string) $value)), fn ($line) => $line !== ''));
    }
}

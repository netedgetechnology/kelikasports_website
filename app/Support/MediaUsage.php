<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Where media assets are used, for many assets at once (one query per source, whatever the number of assets).
 *
 * The CMS refers to files by path or URL (e.g. "media/2026/10/<uuid>.jpg" or ".../storage/media/..."), and only the page
 * sharing image refers to an asset by ID. The server-generated file name appears verbatim in every form, so matching on
 * it finds them all. A match in an image or link field is a confirmed use; a match anywhere else (for example inside
 * body text) is reported as a mention, because it cannot be told apart from text that merely names the file.
 */
class MediaUsage
{
    public const IN_USE = 'in_use';
    public const MENTIONED = 'mentioned';
    public const UNUSED = 'unused';
    public const UNKNOWN = 'unknown';

    /** Every source checked; "unused" means none of these refers to the file. Page revisions are history and not checked. */
    public const SOURCES = ['page sections', 'content items (including archived)', 'site settings', 'menu links', 'redirects', 'page sharing images'];

    private const IMAGE_FIELDS = ['image', 'mobile_image', 'image_path'];
    private const LINK_FIELDS = ['url', 'button_url', 'secondary_button_url', 'map_url', 'video_url', 'registration_url'];

    /**
     * @param iterable<\App\Models\MediaAsset> $assets
     * @return array<int, array{state: string, uses: list<array{label: string, context: ?string, confirmed: bool}>}>
     */
    public static function report(iterable $assets): array
    {
        $names = [];
        $report = [];
        foreach ($assets as $asset) {
            $name = basename(str_replace('\\', '/', (string) $asset->path));
            $known = $name !== '' && str_contains($name, '.');
            $report[$asset->id] = ['state' => $known ? self::UNUSED : self::UNKNOWN, 'uses' => []];
            if ($known) $names[$asset->id] = [$name, trim(json_encode($name, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), '"')];
        }
        if ($names === []) return $report;

        $needles = array_unique(array_merge(...array_values($names)));
        $contains = fn ($query, string $column) => $query->where(function ($query) use ($column, $needles) {
            foreach ($needles as $needle) {
                $query->orWhereRaw($query->getGrammar()->wrap($column)." LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $needle).'%']);
            }
        });
        $owners = fn (?string $text) => array_keys(array_filter($names, fn ($forms) => $text !== null && (str_contains($text, $forms[0]) || str_contains($text, $forms[1]))));
        $add = function (int $id, string $label, ?string $context, bool $confirmed) use (&$report) {
            $report[$id]['uses'][] = ['label' => $label.($confirmed ? '' : ' (mentioned in text)'), 'context' => $context, 'confirmed' => $confirmed];
        };

        $sections = $contains(DB::table('page_sections')->leftJoin('pages', 'pages.id', '=', 'page_sections.page_id'), 'page_sections.data')
            ->get(['page_sections.id', 'page_sections.type', 'page_sections.admin_label', 'page_sections.data', 'pages.slug', 'pages.title', 'pages.deleted_at']);
        foreach ($sections as $row) foreach ($owners($row->data) as $id) {
            $label = "{$row->type} section #{$row->id} on page \"{$row->slug}\"".($row->admin_label ? " (section \"{$row->admin_label}\")" : '').($row->deleted_at ? ' (archived page)' : '');
            $add($id, $label, $row->title, self::confirmedInJson($row->data, $names[$id]));
        }
        foreach ($contains(DB::table('content_items'), 'data')->get(['type', 'title', 'data', 'deleted_at']) as $row) foreach ($owners($row->data) as $id) {
            $add($id, "{$row->type} \"{$row->title}\"".($row->deleted_at ? ' (archived)' : ''), $row->title, self::confirmedInJson($row->data, $names[$id]));
        }
        foreach ($contains(DB::table('settings'), 'value')->get(['key', 'value']) as $row) foreach ($owners($row->value) as $id) {
            $add($id, "site setting {$row->key}", null, str_ends_with($row->key, '_url'));
        }
        foreach ($contains(DB::table('menu_items'), 'url')->get(['label', 'url']) as $row) foreach ($owners($row->url) as $id) {
            $add($id, "menu item \"{$row->label}\"", null, true);
        }
        foreach ($contains(DB::table('redirects'), 'to_path')->get(['from_path', 'to_path']) as $row) foreach ($owners($row->to_path) as $id) {
            $add($id, "redirect from {$row->from_path}", null, true);
        }
        foreach (DB::table('pages')->whereIn('og_media_id', array_keys($report))->get(['og_media_id', 'slug', 'title', 'deleted_at']) as $row) {
            $add((int) $row->og_media_id, "social sharing image of page \"{$row->slug}\"".($row->deleted_at ? ' (archived)' : ''), $row->title, true);
        }

        foreach ($report as $id => $entry) {
            if ($entry['state'] === self::UNKNOWN || $entry['uses'] === []) continue;
            $report[$id]['state'] = in_array(true, array_column($entry['uses'], 'confirmed'), true) ? self::IN_USE : self::MENTIONED;
        }

        return $report;
    }

    /** True when the file name sits in an image or link field of the JSON; false when it only appears elsewhere. */
    private static function confirmedInJson(?string $json, array $forms): bool
    {
        $found = false;
        $walk = function ($value, $key) use (&$walk, &$found, $forms) {
            if ($found) return;
            if (is_array($value)) { foreach ($value as $k => $v) $walk($v, $k); return; }
            if (is_string($value) && str_contains($value, $forms[0]) && in_array((string) $key, [...self::IMAGE_FIELDS, ...self::LINK_FIELDS], true)) $found = true;
        };
        $walk(json_decode((string) $json, true), null);

        return $found;
    }
}

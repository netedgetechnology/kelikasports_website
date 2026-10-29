<?php

namespace App\Support;

use App\Models\ContentItem;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Site settings, navigation menus and footer sports shared by every public view.
 *
 * Built at most once per request (the page, its layout and the 404/search views all ask for it), and cached
 * between requests for visitors. CMS preview always builds it fresh and never caches it, because preview shows
 * draft pages and content. Any saved, deleted or restored setting, menu, menu item, page or content item
 * clears the cache (see AppServiceProvider).
 */
class PublicSiteData
{
    public const CACHE_KEY = 'kelika.public-site-data';

    /** Upper bound on staleness if the database is ever edited outside the CMS (e.g. phpMyAdmin). */
    private const MAX_TTL_SECONDS = 600;

    /** @return array{siteSettings: \Illuminate\Support\Collection, publicMenus: \Illuminate\Support\Collection, footerSports: \Illuminate\Support\Collection} */
    public static function forRequest(Request $request): array
    {
        $data = $request->attributes->get(self::class);
        if (! is_array($data)) {
            $data = self::isPreview() ? self::build(true) : self::cached();
            $request->attributes->set(self::class, $data);
        }

        return $data;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        // Again once the surrounding transaction commits, so a request that read the old rows meanwhile cannot re-cache them.
        DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
    }

    private static function isPreview(): bool
    {
        return auth()->check() && auth()->user()->canManageContent() && session('cms_preview', false);
    }

    private static function cached(): array
    {
        $data = Cache::get(self::CACHE_KEY);
        if (! is_array($data)) {
            $data = self::build(false);
            Cache::put(self::CACHE_KEY, $data, self::ttl());
        }

        return $data;
    }

    /** Never cache past the moment a scheduled page goes live, so it appears in the menus on time. */
    private static function ttl(): int
    {
        $next = Page::query()->upcoming()->min('published_at');

        return $next ? max(1, min(self::MAX_TTL_SECONDS, (int) ceil(now()->diffInSeconds(Carbon::parse($next))))) : self::MAX_TTL_SECONDS;
    }

    private static function build(bool $preview): array
    {
        $settings = Branding::apply(Setting::query()->whereIn('group', ['general', 'branding', 'contact', 'social'])->get()->mapWithKeys(fn ($item) => [$item->key => $item->value]));

        $menus = Menu::query()->where('is_active', true)->with(['items' => fn ($query) => $query->where('is_visible', true), 'items.children' => fn ($query) => $query->where('is_visible', true)])->get()->keyBy('location');
        // Links to pages that are not live (draft, scheduled, archived, missing, or kept out of menus) are left out;
        // the menu items themselves are untouched, so they reappear as soon as their page is live.
        $links = MenuLinks::load();
        $isPageVisible = fn ($item) => $links->isShown((string) $item->url, $preview);
        foreach ($menus as $menu) {
            foreach ($menu->items as $rootItem) {
                $rootItem->setRelation('children', $rootItem->children->filter($isPageVisible)->values());
            }
            $menu->setRelation('items', $menu->items->filter($isPageVisible)->values());
        }

        $sportsQuery = ContentItem::query()->ofType('sport')->orderBy('sort_order');
        if (! $preview) $sportsQuery->published();

        return ['siteSettings' => $settings, 'publicMenus' => $menus, 'footerSports' => $sportsQuery->limit(5)->get(), 'footerLegal' => self::legalLinks($settings)];
    }

    /**
     * Slugs of the legal pages for the footer: the page chosen in Global Settings, else a page at one of the usual
     * addresses; in either case only while it is published (the first match wins). Otherwise null, so the footer never
     * links to a page that is not there. Slugs, not URLs: this data is cached, and URLs are built per request.
     *
     * @return array{terms: ?string, privacy: ?string}
     */
    private static function legalLinks(\Illuminate\Support\Collection $settings): array
    {
        $chosen = fn (string $key) => is_string($settings->get($key)) && $settings->get($key) !== '' ? [$settings->get($key)] : [];
        $slugs = ['terms' => [...$chosen('terms_page'), 'terms-of-service', 'terms', 'terms-and-conditions'], 'privacy' => [...$chosen('privacy_page'), 'privacy-policy', 'privacy']];
        $live = Page::query()->published()->whereIn('slug', array_merge(...array_values($slugs)))->pluck('slug')->all();
        $first = fn (array $candidates) => collect($candidates)->first(fn ($slug) => in_array($slug, $live, true));

        return array_map(fn ($candidates) => $first($candidates), $slugs);
    }
}

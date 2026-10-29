<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Support\Collection;

/**
 * Works out where a menu link goes, so menus never send visitors to a page that is not live.
 *
 * Menu items store a free-text URL. A link counts as a link to a CMS page only when its path is a single page slug
 * ("/about", or "/" for the homepage); external URLs, mailto/tel links, anchors, nested paths, files and system
 * routes such as /search are custom links and are always shown. A page link is shown only when that page is
 * published (and not set to stay out of menus). A slug with no page but an active redirect follows the redirect once.
 */
class MenuLinks
{
    public const CUSTOM = 'custom';
    public const PUBLISHED = 'published';
    public const NOT_IN_MENU = 'not_in_menu';
    public const DRAFT = 'draft';
    public const SCHEDULED = 'scheduled';
    public const ARCHIVED = 'archived';
    public const MISSING = 'missing';

    /** @param Collection<string, Page> $pages keyed by slug, including archived pages */
    private function __construct(private Collection $pages, private Collection $redirects) {}

    /** Loads every page (including archived ones) and every active redirect: two queries for any number of links. */
    public static function load(): self
    {
        return new self(
            Page::withTrashed()->get(['id', 'slug', 'status', 'published_at', 'show_in_menu', 'deleted_at'])->keyBy('slug'),
            Redirect::query()->where('is_active', true)->pluck('to_path', 'from_path'),
        );
    }

    /** @return array{state: string, slug: ?string, via: ?string, page: ?Page} */
    public function resolve(string $url): array
    {
        $slug = self::pageSlug($url);
        if ($slug === null) return ['state' => self::CUSTOM, 'slug' => null, 'via' => null, 'page' => null];

        $page = $this->pages->get($slug);
        if ($page) return ['state' => self::pageState($page), 'slug' => $slug, 'via' => null, 'page' => $page];

        // No page here: a redirect (for example from a renamed page) may still lead somewhere. Follow it once.
        $target = $this->redirects->get('/'.$slug);
        if ($target !== null) {
            $targetSlug = self::pageSlug((string) $target);
            $targetPage = $targetSlug !== null ? $this->pages->get($targetSlug) : null;
            if ($targetSlug === null) return ['state' => self::CUSTOM, 'slug' => $slug, 'via' => (string) $target, 'page' => null];

            return ['state' => $targetPage ? self::pageState($targetPage) : self::MISSING, 'slug' => $targetSlug, 'via' => '/'.$slug, 'page' => $targetPage];
        }

        return ['state' => self::MISSING, 'slug' => $slug, 'via' => null, 'page' => null];
    }

    /** Whether the link may appear in a public menu. CMS preview also shows links to draft and scheduled pages. */
    public function isShown(string $url, bool $preview = false): bool
    {
        $state = $this->resolve($url)['state'];

        return in_array($state, [self::CUSTOM, self::PUBLISHED], true) || ($preview && in_array($state, [self::DRAFT, self::SCHEDULED], true));
    }

    /** Why a link is hidden from visitors, for the admin menu editor; null when it is shown. */
    public function hiddenReason(string $url): ?string
    {
        $link = $this->resolve($url);
        $path = $link['slug'] === 'home' ? '/' : '/'.$link['slug'];
        $via = $link['via'] ? " (the link redirects there from {$link['via']})" : '';

        return match ($link['state']) {
            self::DRAFT => "the page at {$path} is a draft{$via}. It will appear once the page is published.",
            self::SCHEDULED => "the page at {$path} is scheduled for ".$link['page']->published_at->format('d M Y H:i')."{$via}. It will appear then.",
            self::ARCHIVED => "the page at {$path} is archived{$via}. Restore and publish it, or change this link.",
            self::MISSING => "no page exists at {$path}{$via}. Change this link to an existing page or a full URL.",
            self::NOT_IN_MENU => "the page at {$path} is set to stay out of menus{$via}.",
            default => null,
        };
    }

    /** The page slug a URL points to, or null for anything that is not a single-slug site path. */
    public static function pageSlug(string $url): ?string
    {
        $url = trim($url);
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) return null;
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) return null;

        $slug = trim($path, '/');
        if ($slug === '') return 'home';

        return preg_match('/^[a-z0-9-]+$/', $slug) && ! ReservedPaths::isReserved($slug) ? $slug : null;
    }

    private static function pageState(Page $page): string
    {
        return match ($page->publicationState()) {
            'archived' => self::ARCHIVED,
            Page::DRAFT => self::DRAFT,
            Page::SCHEDULED => self::SCHEDULED,
            default => $page->show_in_menu ? self::PUBLISHED : self::NOT_IN_MENU,
        };
    }
}

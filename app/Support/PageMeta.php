<?php

namespace App\Support;

use App\Models\Page;

/**
 * Page metadata for the public <head> and the sitemap: canonical URL, Open Graph and Twitter/X card values.
 *
 * Every URL the site generates for itself is absolute and built from APP_URL, never from the request's Host header,
 * so development and production each name their own canonical host. A page's configured canonical URL is used as is
 * in its HTML (it may deliberately point elsewhere); the sitemap only ever lists this site's own addresses.
 */
class PageMeta
{
    /** The site's own address for a page: https://host/ for the homepage, https://host/slug otherwise. */
    public static function pageUrl(Page $page): string
    {
        return self::absolute($page->slug === 'home' ? '/' : '/'.$page->slug);
    }

    /** The page's configured canonical URL, or its own address when none is set. */
    public static function canonical(Page $page): string
    {
        return filled($page->canonical_url) ? $page->canonical_url : self::pageUrl($page);
    }

    /** For the sitemap: the configured canonical URL only when it is on this site's own host, else the page's address. */
    public static function sitemapUrl(Page $page): string
    {
        $host = fn (?string $url) => strtolower((string) parse_url((string) $url, PHP_URL_HOST));

        return filled($page->canonical_url) && $host($page->canonical_url) === $host(config('app.url'))
            ? $page->canonical_url
            : self::pageUrl($page);
    }

    /**
     * Absolute URL of the sharing image: the page's own (only if it is an available library image), else the global
     * social-sharing image setting. Null when neither is set: no image is ever invented.
     */
    public static function image(?Page $page, array|\ArrayAccess $settings): ?string
    {
        $asset = $page?->og_media_id ? MediaPicker::find($page->og_media_id) : null;
        if ($asset) return self::absolute($asset->url);

        $value = trim((string) ($settings['social_image_url'] ?? ''));

        return $value === '' ? null : self::absolute((string) Media::url($value));
    }

    /**
     * Everything the <head> needs. Overrides win in this order: Open Graph fields, then SEO fields, then the page
     * title, then the site's name and tagline (also used by pages that are not CMS pages, such as search).
     *
     * @return array{title: string, description: string, canonical: ?string, url: ?string, image: ?string, site_name: string, card: string}
     */
    public static function for(?Page $page, array|\ArrayAccess $settings): array
    {
        $siteName = (string) ($settings['site_name'] ?? 'KELIKA');
        $tagline = (string) ($settings['site_tagline'] ?? '');
        $canonical = $page ? self::canonical($page) : null;
        $image = self::image($page, $settings);

        return [
            'title' => (string) ($page?->og_title ?: ($page?->seo_title ?: ($page?->title ?: $siteName))),
            'description' => (string) ($page?->og_description ?: ($page?->seo_description ?: $tagline)),
            'canonical' => $canonical,
            'url' => $canonical,
            'image' => $image,
            'site_name' => $siteName,
            'card' => $image ? 'summary_large_image' : 'summary',
        ];
    }

    /** Makes a root-relative path absolute on APP_URL; absolute http(s) URLs are returned unchanged. */
    public static function absolute(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) return $url;

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }
}

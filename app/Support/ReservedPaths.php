<?php

namespace App\Support;

/**
 * First URL path segments that the application or the web server already answers. Pages and redirects cannot use
 * them, because the route or static file would always win (or the page would hide it).
 */
class ReservedPaths
{
    public const SEGMENTS = [
        'admin', 'search', 'up', 'build', 'css', 'images', 'js', 'storage', 'sitemap.xml', 'robots.txt', 'index.php',
    ];

    public static function isReserved(string $segment): bool
    {
        $segment = strtolower(trim($segment, '/'));

        // Anything that exists at the top of public/ is served by Apache before Laravel sees the request.
        return in_array($segment, self::SEGMENTS, true)
            || ($segment !== '' && ! str_contains($segment, '..') && file_exists(public_path($segment)));
    }
}

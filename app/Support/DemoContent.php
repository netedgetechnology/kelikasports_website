<?php

namespace App\Support;

use App\Models\ContentItem;

/**
 * Unverified demo content (design-reference events, testimonials, statistics) and where visitors may see it.
 */
class DemoContent
{
    /** Whether visitors on this request may see demo content. Never true in production. */
    public static function publiclyVisible(): bool
    {
        if (app()->environment('production') || ! config('kelika.demo_content.public')) return false;

        return in_array(strtolower(request()->getHost()), array_map('strtolower', config('kelika.demo_content.hosts', [])), true);
    }

    public static function isDemo(ContentItem $item): bool
    {
        return (bool) ($item->data['demo'] ?? false);
    }
}

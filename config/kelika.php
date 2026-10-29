<?php

return [

    /*
    | Demo content: unverified records flagged "demo" (reference events, testimonials, statistics).
    | Visitors see them only when every guard passes: the app is not in production, this flag is on
    | (default: on for local/development/staging environments) and the request host is allowlisted.
    | CMS preview always shows them. Search and the sitemap never do.
    */
    'demo_content' => [
        'public' => (bool) env('KELIKA_PUBLIC_DEMO_CONTENT', in_array(env('APP_ENV'), ['local', 'development', 'staging'], true)),
        'hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('KELIKA_DEMO_HOSTS', 'dev.kelikasports.com'))))),
    ],

    /*
    | Image optimisation (App\Support\ImageOptimizer). Originals are always kept; these settings only shape the
    | WebP copies generated from them. Change "version" whenever widths or quality change so that
    | `php artisan kelika:images --write` knows which images to regenerate.
    |
    | - quality: WebP quality for photos (JPEG/WebP sources) and for graphics (PNG sources, which often hold logos,
    |   text or transparency). Trials on the site's photos at 80 gave PSNR 47-53 dB, visually indistinguishable.
    | - widths: candidate widths; never larger than the source (no upscaling).
    | - min_saving: a full-size copy is kept only if it is at least this much smaller than the original file.
    | - max_pixels: larger images are stored as originals but not processed (GD needs ~4 bytes per pixel).
    */
    'images' => [
        'version' => 1,
        'quality' => ['photo' => 80, 'graphic' => 90],
        'widths' => [400, 800, 1200, 1600, 2000],
        'min_saving' => 0.10,
        'max_pixels' => 24_000_000,
    ],

];

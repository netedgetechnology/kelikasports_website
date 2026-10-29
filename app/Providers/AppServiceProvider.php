<?php

namespace App\Providers;

use App\Models\ContentItem;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Setting;
use App\Support\PublicSiteData;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Each of these views needs the shared data; PublicSiteData builds it only once per request.
        View::composer(['public.page','public.search','errors.404','layouts.public'], function ($view): void {
            $view->with(PublicSiteData::forRequest(request()));
        });

        // Every CMS change to data the public layout shows clears the cached copy. Redirects count too: a menu link to a
        // renamed page's old address follows its redirect. Writes go through Eloquent; the bulk redirect updates made
        // when a page is renamed happen together with that page's own save, which clears the cache.
        foreach ([Setting::class, Menu::class, MenuItem::class, Page::class, ContentItem::class, Redirect::class] as $model) {
            Event::listen(["eloquent.saved: {$model}", "eloquent.deleted: {$model}", "eloquent.restored: {$model}"], fn () => PublicSiteData::forget());
        }
    }
}

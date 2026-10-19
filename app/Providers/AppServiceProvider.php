<?php

namespace App\Providers;

use App\Models\Menu;
use App\Models\ContentItem;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::composer(['public.page','public.search','errors.404','layouts.public'], function ($view): void {
            $view->with('siteSettings', Setting::query()->whereIn('group', ['general','branding','contact','social'])->get()->mapWithKeys(fn ($item) => [$item->key => $item->value]));
            $menus = Menu::query()->where('is_active', true)->with(['items' => fn ($query) => $query->where('is_visible', true), 'items.children' => fn ($query) => $query->where('is_visible', true)])->get()->keyBy('location');
            $pageQuery = Page::query();
            if (!(auth()->check() && auth()->user()->canManageContent() && session('cms_preview',false))) $pageQuery->where('status','published')->whereNotNull('published_at')->where('published_at','<=',now());
            $pageVisibility = $pageQuery->pluck('show_in_menu','slug');
            foreach ($menus as $menu) {
                $isPageVisible = function ($item) use ($pageVisibility) {
                    $path = parse_url($item->url, PHP_URL_PATH);
                    if (!is_string($path) || !str_starts_with($item->url, '/')) return true;
                    $slug = trim($path, '/');
                    if ($slug === '') $slug = 'home';
                    return !$pageVisibility->has($slug) || (bool) $pageVisibility->get($slug);
                };
                foreach ($menu->items as $rootItem) {
                    $rootItem->setRelation('children', $rootItem->children->filter($isPageVisible)->values());
                }
                $menu->setRelation('items', $menu->items->filter($isPageVisible)->values());
            }
            $view->with('publicMenus', $menus);
            $sportsQuery = ContentItem::query()->ofType('sport')->orderBy('sort_order');
            if (!(auth()->check() && auth()->user()->canManageContent() && session('cms_preview', false))) $sportsQuery->published();
            $view->with('footerSports', $sportsQuery->limit(5)->get());
        });
    }
}

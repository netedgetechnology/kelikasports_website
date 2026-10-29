<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\Page;
use App\Models\Redirect;
use App\Support\DemoContent;
use Illuminate\Http\Request;

class PublicPageController extends Controller
{
    public function home()
    {
        $preview = $this->previewAllowed();
        $query = Page::query()->where('slug', 'home');
        if (! $preview) $query->published();

        $page = $query->with(['sections' => fn ($builder) => $builder->where('is_visible', true)->orderBy('sort_order')])->firstOrFail();

        return $this->renderPage($page, $preview);
    }

    public function show(Request $request, string $slug)
    {
        $preview = $this->previewAllowed();
        $query = Page::query()->where('slug', $slug);
        if (! $preview) $query->published();

        // A live page always wins over a redirect from its own path, so a stale redirect can never hide it or loop.
        $page = $query->with(['sections' => fn ($builder) => $builder->where('is_visible', true)->orderBy('sort_order')])->first();
        if ($page) return $this->renderPage($page, $preview);

        return $this->redirectOrNotFound($request, $preview);
    }

    /** Paths no other route matches (nested, uppercase, legacy file names): follow a redirect or show the 404 page. */
    public function fallback(Request $request)
    {
        return $this->redirectOrNotFound($request, $this->previewAllowed());
    }

    private function redirectOrNotFound(Request $request, bool $preview)
    {
        $redirect = $preview ? null : Redirect::forPath($request->path());
        abort_unless($redirect, 404);

        // Never send a visitor back to the path they asked for.
        $target = $redirect->to_path;
        abort_if(str_starts_with($target, '/') && Redirect::normalizePath(parse_url($target, PHP_URL_PATH) ?: '/') === Redirect::normalizePath($request->path()), 404);

        return redirect($target, $redirect->status_code);
    }

    public function preview(Request $request, Page $page)
    {
        abort_unless(auth()->check() && auth()->user()->canManageContent(), 403);
        $request->session()->put('cms_preview', true);
        $page->load(['sections' => fn ($builder) => $builder->where('is_visible', true)->orderBy('sort_order')]);

        return $this->renderPage($page, true);
    }

    public function exitPreview(Request $request)
    {
        $request->session()->forget('cms_preview');

        return redirect()->route('home');
    }

    private function previewAllowed(): bool
    {
        return session('cms_preview', false) && auth()->check() && auth()->user()->canManageContent();
    }

    private function renderPage(Page $page, bool $preview = false)
    {
        $page->loadMissing(['sections' => fn ($builder) => $builder->where('is_visible', true)->orderBy('sort_order')]);
        // Section templates read $section->page; hand them this page instead of querying it once per section.
        $page->sections->each->setRelation('page', $page);

        // Demo records (unverified) are shown in CMS preview, and to visitors only on an allowlisted non-production host.
        $demoMode = ! $preview && DemoContent::publiclyVisible();

        $items = ContentItem::query()
            ->when(! $preview, fn ($query) => $query->whereIn('status', $demoMode ? ['published', 'draft'] : ['published']))
            ->whereIn('type', ['event', 'sport', 'program', 'facility', 'coach', 'faq', 'gallery', 'testimonial'])
            ->orderBy('sort_order')
            ->get()
            // Outside preview: real records must be published; demo records appear only in demo mode, whatever their status.
            ->when(! $preview, fn ($collection) => $collection->filter(fn ($item) => DemoContent::isDemo($item) ? $demoMode : $item->status === 'published'))
            ->groupBy('type');

        $allEvents = $items->get('event', collect())
            ->filter(fn ($item) => ! $item->starts_at || $item->starts_at->greaterThanOrEqualTo(now()->subDays(1)))
            ->sortBy(fn ($item) => $item->starts_at?->timestamp ?? PHP_INT_MAX)
            ->values();
        $featuredEvent = $allEvents->first(fn ($event) => (bool) ($event->data['featured'] ?? false));
        $upcomingEvents = $allEvents->reject(fn ($event) => $featuredEvent && $event->id === $featuredEvent->id)->take($featuredEvent ? 4 : 4)->values();
        $events = $featuredEvent ? collect([$featuredEvent])->merge($upcomingEvents) : $upcomingEvents;

        $sports = $items->get('sport', collect());
        $programs = $items->get('program', collect());
        $facilities = $items->get('facility', collect());
        $coaches = $items->get('coach', collect());
        $faqs = $items->get('faq', collect());
        $galleries = $items->get('gallery', collect());
        $testimonials = $items->get('testimonial', collect());

        $response = response()->view('public.page', compact(
            'page', 'preview', 'demoMode', 'events', 'featuredEvent', 'upcomingEvents', 'sports', 'programs', 'facilities', 'coaches', 'faqs', 'galleries', 'testimonials'
        ));
        if ($preview || $demoMode) $response->header('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}

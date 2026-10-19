<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\Page;
use App\Models\Redirect;
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
        $path = '/'.ltrim($slug, '/');

        if (! $preview) {
            $redirect = Redirect::query()->where('from_path', $path)->where('is_active', true)->first();
            if ($redirect) return redirect($redirect->to_path, $redirect->status_code);
        }

        $query = Page::query()->where('slug', $slug);
        if (! $preview) $query->published();

        $page = $query->with(['sections' => fn ($builder) => $builder->where('is_visible', true)->orderBy('sort_order')])->firstOrFail();

        return $this->renderPage($page, $preview);
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

        $items = ContentItem::query()
            ->when(! $preview, fn ($query) => $query->where('status', 'published'))
            ->whereIn('type', ['event', 'sport', 'program', 'facility', 'coach', 'faq', 'gallery', 'testimonial'])
            ->orderBy('sort_order')
            ->get()
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
            'page', 'preview', 'events', 'featuredEvent', 'upcomingEvents', 'sports', 'programs', 'facilities', 'coaches', 'faqs', 'galleries', 'testimonials'
        ));
        if ($preview) $response->header('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}

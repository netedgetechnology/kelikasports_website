<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Redirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $pages = Page::with('creator')->orderBy('sort_order')->orderBy('title')->paginate(20);

        return view('admin.pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.pages.form', ['page' => new Page(), 'isNew' => true]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($data['status'] === 'published') {
            throw ValidationException::withMessages(['status' => 'Create the page as a draft, add its sections and hero image, then publish it.']);
        }

        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $page = Page::create($data);

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Page created. Add sections to build the page.');
    }

    public function edit(Page $page)
    {
        $page->load(['sections', 'revisions.user']);

        return view('admin.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $data = $this->validated($request, $page);

        if ($data['status'] === 'published' && $page->status !== 'published') {
            $this->assertPublishable($page);
            $data['published_at'] = now();
        }
        if ($page->slug === 'home' && $data['slug'] !== 'home') {
            throw ValidationException::withMessages(['slug' => 'The homepage slug is reserved and cannot be changed.']);
        }

        $oldSlug = $page->slug;
        $wasPublished = $page->status === 'published' && $page->published_at && $page->published_at->isPast();
        $data['updated_by'] = $request->user()->id;

        PageRevision::create([
            'page_id' => $page->id,
            'user_id' => $request->user()->id,
            'revision_label' => 'Before update',
            'snapshot' => [
                'page' => $page->only(['title', 'slug', 'template', 'status', 'seo_title', 'seo_description']),
                'sections' => $page->sections()->orderBy('sort_order')->get()->toArray(),
            ],
        ]);

        $page->update($data);

        if ($wasPublished && $page->status === 'published' && $oldSlug !== $page->slug) {
            Redirect::query()->firstOrCreate(
                ['from_path' => '/'.$oldSlug],
                ['to_path' => $page->slug === 'home' ? '/' : '/'.$page->slug, 'status_code' => 301, 'is_active' => true]
            );
        }

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Page settings saved.');
    }

    public function destroy(Page $page)
    {
        abort_unless(auth()->user()->isAdministrator(), 403, 'Only administrators can archive pages.');
        abort_if(in_array($page->slug, ['home', 'contact'], true), 422, 'Core pages cannot be deleted.');
        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', 'Page moved to the archive.');
    }

    public function publish(Page $page)
    {
        $this->assertPublishable($page);
        $page->update([
            'status' => 'published',
            'published_at' => $page->status === 'published' && $page->published_at ? $page->published_at : now(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Page published.');
    }

    public function unpublish(Page $page)
    {
        $page->update(['status' => 'draft', 'updated_by' => auth()->id()]);

        return back()->with('success', 'Page changed to draft and removed from public view.');
    }

    private function assertPublishable(Page $page): void
    {
        $hero = $page->sections()->where('type', 'hero')->where('is_visible', true)->first();
        $heroData = $hero?->data ?? [];
        $image = $heroData['image'] ?? null;

        if (! is_string($image) || trim($image) === '') {
            throw ValidationException::withMessages(['publish' => 'Add an image to a visible Hero section before publishing this page.']);
        }
        if (str_starts_with($image, 'media/') && ! Storage::disk('public')->exists($image)) {
            throw ValidationException::withMessages(['publish' => 'The selected Hero image file is missing from storage. Re-select or upload the image before publishing.']);
        }
        if (str_starts_with($image, '/images/') && ! is_file(public_path(ltrim($image, '/')))) {
            throw ValidationException::withMessages(['publish' => 'The selected Hero image file is missing from public/images. Re-select or upload the image before publishing.']);
        }
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(['search', 'admin']), Rule::unique('pages', 'slug')->ignore($page?->id),
            ],
            'template' => ['required', Rule::in(['standard', 'landing'])],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'show_in_menu' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'og_media_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'robots_directive' => ['required', Rule::in(['index,follow', 'noindex,follow', 'noindex,nofollow'])],
            'og_title' => ['nullable', 'string', 'max:120'],
            'og_description' => ['nullable', 'string', 'max:320'],
        ]);

        $data['show_in_menu'] = $request->boolean('show_in_menu');
        $data['sort_order'] = (int) ($request->input('sort_order') ?? 0);

        return $data;
    }
}

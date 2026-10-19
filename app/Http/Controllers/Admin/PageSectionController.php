<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PageSectionController extends Controller
{
    private const IMAGE_POSITIONS = ['center', 'top', 'bottom', 'left', 'right', 'center top', 'center bottom', 'left center', 'right center'];

    public function store(Request $request, Page $page)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(PageSection::TYPES)],
            'admin_label' => ['nullable', 'string', 'max:120'],
        ]);

        $this->recordRevision($page, 'Before adding section');
        $section = $page->sections()->create([
            'type' => $validated['type'],
            'admin_label' => $validated['admin_label'] ?? null,
            'sort_order' => ((int) $page->sections()->max('sort_order')) + 1,
            'is_visible' => true,
            'data' => $this->defaults($validated['type']),
        ]);

        return redirect()->route('admin.pages.sections.edit', [$page, $section])
            ->with('success', 'Section added. Configure its content below.');
    }

    public function edit(Page $page, PageSection $section)
    {
        abort_unless($section->page_id === $page->id, 404);

        return view('admin.sections.edit', compact('page', 'section'));
    }

    public function update(Request $request, Page $page, PageSection $section)
    {
        abort_unless($section->page_id === $page->id, 404);

        $validated = $request->validate([
            'admin_label' => ['nullable', 'string', 'max:120'],
            'is_visible' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:240'],
            'eyebrow' => ['nullable', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:12000'],
            'image' => ['nullable', 'string', 'max:2048'],
            'mobile_image' => ['nullable', 'string', 'max:2048'],
            'image_position' => ['nullable', Rule::in(self::IMAGE_POSITIONS)],
            'image_alt' => ['nullable', 'string', 'max:250'],
            'button_label' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'string', 'max:2048'],
            'map_url' => ['nullable', 'string', 'max:2048'],
            'secondary_button_label' => ['nullable', 'string', 'max:80'],
            'secondary_button_url' => ['nullable', 'string', 'max:2048'],
            'tone' => ['nullable', Rule::in(['light', 'green', 'terracotta', 'gold', 'soft'])],
            'items_json' => ['nullable', 'string', 'max:30000'],
        ]);

        $data = $section->data ?? [];
        foreach ([
            'title', 'eyebrow', 'subtitle', 'body', 'image', 'mobile_image', 'image_position',
            'image_alt', 'button_label', 'button_url', 'map_url', 'secondary_button_label',
            'secondary_button_url', 'tone',
        ] as $field) {
            $data[$field] = $validated[$field] ?? ($field === 'image_position' ? 'center' : '');
        }

        if (! empty($validated['items_json'])) {
            $items = json_decode($validated['items_json'], true);
            if (! is_array($items) || json_last_error() !== JSON_ERROR_NONE || ! array_is_list($items)) {
                return back()->withErrors(['items_json' => 'Items must be a valid JSON array.'])->withInput();
            }
            $this->validateStructuredValues($items);
            $data['items'] = $items;
        } elseif (array_key_exists('items_json', $validated)) {
            $data['items'] = [];
        }

        foreach (['button_url', 'secondary_button_url', 'map_url'] as $urlField) {
            if (! SafeUrl::isSafe($data[$urlField] ?? null)) {
                return back()->withErrors([$urlField => 'Use a relative site path or a valid http/https URL.'])->withInput();
            }
        }
        foreach (['image', 'mobile_image'] as $imageField) {
            if (! SafeUrl::isSafe($data[$imageField] ?? null, true)) {
                return back()->withErrors([$imageField => 'Use a media library path or a valid http/https image URL.'])->withInput();
            }
        }
        if ($page->status === 'published' && $section->type === 'hero' && (! $request->boolean('is_visible') || empty($data['image']))) {
            return back()->withErrors(['publish' => 'A published page must keep a visible Hero section with an image. Unpublish the page before removing or hiding its Hero.'])->withInput();
        }

        $this->recordRevision($page, 'Before editing section');
        $section->update([
            'admin_label' => $validated['admin_label'] ?? null,
            'is_visible' => $request->boolean('is_visible'),
            'data' => $data,
        ]);

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Section saved.');
    }

    public function destroy(Page $page, PageSection $section)
    {
        abort_unless($section->page_id === $page->id, 404);
        if ($page->status === 'published' && $section->type === 'hero') {
            return back()->withErrors(['publish' => 'Unpublish the page before deleting its Hero section.']);
        }
        $this->recordRevision($page, 'Before deleting section');
        $section->delete();
        $this->normalizeOrder($page);

        return back()->with('success', 'Section removed.');
    }

    public function duplicate(Page $page, PageSection $section)
    {
        abort_unless($section->page_id === $page->id, 404);
        $this->recordRevision($page, 'Before duplicating section');
        $copy = $section->replicate();
        $copy->admin_label = trim(($section->admin_label ?: ucfirst(str_replace('_', ' ', $section->type))).' copy');
        $copy->sort_order = ((int) $page->sections()->max('sort_order')) + 1;
        $copy->save();

        return back()->with('success', 'Section duplicated.');
    }

    public function move(Page $page, PageSection $section, string $direction)
    {
        abort_unless($section->page_id === $page->id, 404);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $sections = $page->sections()->orderBy('sort_order')->orderBy('id')->get();
        $index = $sections->search(fn ($item) => $item->id === $section->id);
        if ($index === false) return back()->withErrors(['section' => 'The section could not be found on this page.']);

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if (! isset($sections[$swapIndex])) return back()->with('success', 'Section is already at that end of the page.');

        $this->recordRevision($page, 'Before reordering sections');
        $current = $sections[$index];
        $other = $sections[$swapIndex];
        [$current->sort_order, $other->sort_order] = [$other->sort_order, $current->sort_order];
        $current->save();
        $other->save();
        $this->normalizeOrder($page);

        return back()->with('success', 'Section order updated.');
    }

    private function validateStructuredValues(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $this->validateStructuredValues($value);
                continue;
            }
            if (! is_string($value)) continue;

            $field = (string) $key;
            if (in_array($field, ['url', 'button_url', 'secondary_button_url', 'map_url'], true) && ! SafeUrl::isSafe($value)) {
                throw ValidationException::withMessages(['items_json' => 'An item contains an unsafe URL. Use a relative path or http/https URL.']);
            }
            if (in_array($field, ['image', 'image_path', 'mobile_image'], true) && ! SafeUrl::isSafe($value, true)) {
                throw ValidationException::withMessages(['items_json' => 'An item contains an unsafe image path. Use the media library path or an http/https URL.']);
            }
            if ($field === 'color' && ! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                throw ValidationException::withMessages(['items_json' => 'Item colors must be six-digit hex values.']);
            }
            if ($field === 'image_position' && ! in_array($value, self::IMAGE_POSITIONS, true)) {
                throw ValidationException::withMessages(['items_json' => 'An item contains an unsupported image focal position.']);
            }
        }
    }

    private function normalizeOrder(Page $page): void
    {
        $page->sections()->orderBy('sort_order')->orderBy('id')->get()->values()
            ->each(fn ($section, $index) => $section->update(['sort_order' => $index + 1]));
    }

    private function recordRevision(Page $page, string $label): void
    {
        PageRevision::create([
            'page_id' => $page->id,
            'user_id' => auth()->id(),
            'revision_label' => $label,
            'snapshot' => [
                'page' => $page->only(['title', 'slug', 'template', 'status', 'seo_title', 'seo_description', 'canonical_url', 'robots_directive']),
                'sections' => $page->sections()->orderBy('sort_order')->get()->toArray(),
            ],
        ]);
    }

    private function defaults(string $type): array
    {
        return [
            'eyebrow' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'image' => '',
            'mobile_image' => '', 'image_position' => 'center', 'image_alt' => '', 'button_label' => '',
            'button_url' => '', 'secondary_button_label' => '', 'secondary_button_url' => '',
            'map_url' => '', 'tone' => 'light', 'items' => [],
        ];
    }
}

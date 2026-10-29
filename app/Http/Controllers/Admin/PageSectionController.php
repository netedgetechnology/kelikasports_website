<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Support\MediaPicker;
use App\Support\SafeUrl;
use App\Support\SectionDefaults;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PageSectionController extends Controller
{
    private const IMAGE_POSITIONS = ['center', 'top', 'bottom', 'left', 'right', 'center top', 'center bottom', 'left center', 'right center'];

    /** Section types whose form shows the desktop/mobile image fields (and their media library pickers). */
    public const IMAGE_SECTION_TYPES = ['hero', 'split', 'cta', 'stats', 'feature_list', 'card_grid', 'facilities_showcase', 'event_list'];

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

        // Thumbnails and names for the images this section uses now, looked up together.
        $imagePreviews = MediaPicker::previews(MediaPicker::imageValues($section->data ?? []));

        return view('admin.sections.edit', compact('page', 'section', 'imagePreviews'));
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
            // Set by the media library picker; resolved below to the asset's stored path.
            'image_media_id' => ['nullable', 'integer', Rule::prohibitedIf(! in_array($section->type, self::IMAGE_SECTION_TYPES, true))],
            'mobile_image_media_id' => ['nullable', 'integer', Rule::prohibitedIf(! in_array($section->type, self::IMAGE_SECTION_TYPES, true))],
            // The presets, or the custom value this section already stores (e.g. "44% center"), so re-saving keeps it.
            'image_position' => ['nullable', Rule::in([...self::IMAGE_POSITIONS, ...array_filter([$section->data['image_position'] ?? null], 'is_string')])],
            'image_alt' => ['nullable', 'string', 'max:250'],
            'button_label' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'string', 'max:2048'],
            'map_url' => ['nullable', 'string', 'max:2048'],
            'secondary_button_label' => ['nullable', 'string', 'max:80'],
            'secondary_button_url' => ['nullable', 'string', 'max:2048'],
            'tone' => ['nullable', Rule::in(['light', 'green', 'terracotta', 'gold', 'soft'])],
            'items_json' => ['nullable', 'string', 'max:30000'],
            'heading_accent' => ['nullable', Rule::in(SectionDefaults::HEADING_ACCENTS)],
            'layout' => ['nullable', Rule::in(array_keys(SectionDefaults::LAYOUTS[$section->type] ?? []))],
            'overlay_darkness' => ['nullable', 'integer', 'min:0', 'max:'.SectionDefaults::OVERLAY_MAX, Rule::prohibitedIf($section->type !== 'hero')],
            'script_text' => ['nullable', 'string', 'max:120'],
            'values_text' => ['nullable', 'string', 'max:160'],
            'video_url' => ['nullable', 'string', 'max:2048'],
            'video_label' => ['nullable', 'string', 'max:80'],
        ]);

        $data = $section->data ?? [];
        foreach ([
            'title', 'eyebrow', 'subtitle', 'body', 'image', 'mobile_image', 'image_position',
            'image_alt', 'button_label', 'button_url', 'map_url', 'secondary_button_label',
            'secondary_button_url', 'tone', 'heading_accent', 'layout', 'script_text', 'values_text',
            'video_url', 'video_label',
        ] as $field) {
            // Each section type's form shows only some of these fields. A field it submits is saved, including an
            // emptied one (stored as ""); a field it does not show keeps whatever value the section already has.
            if (! $request->has($field)) continue;
            $data[$field] = $validated[$field] ?? ($field === 'image_position' ? 'center' : '');
        }

        // A picked image replaces whatever the path box holds. Only an existing, supported image whose file is on disk
        // can be picked, and it is stored as its original media/… path: the format templates and WebP copies expect.
        foreach (['image', 'mobile_image'] as $imageField) {
            if (! filled($validated[$imageField.'_media_id'] ?? null)) continue;
            $asset = MediaPicker::find($validated[$imageField.'_media_id']);
            if (! $asset) {
                return back()->withErrors([$imageField => 'The selected image is no longer available in the media library. Choose another image.'])->withInput();
            }
            $data[$imageField] = $asset->path;
        }

        // Hero overlay darkness: stored only once it differs from what the section shows now, so saving an untouched
        // form leaves the data as it was. Empty restores the default.
        if ($request->has('overlay_darkness')) {
            $overlay = $validated['overlay_darkness'] ?? null;
            if ($overlay === null) unset($data['overlay_darkness']);
            elseif ((int) $overlay !== SectionDefaults::overlayDarkness($section) || array_key_exists('overlay_darkness', $data)) $data['overlay_darkness'] = (int) $overlay;
        }

        if ($section->type === 'stats') $data['demo_content'] = $request->boolean('demo_content');
        // Hero figures (items with a value) and gallery images: the flag is stored once ticked, so saving a section that never used it leaves its data as it was.
        if (in_array($section->type, ['hero', 'gallery'], true) && ($request->boolean('demo_content') || array_key_exists('demo_content', $data))) $data['demo_content'] = $request->boolean('demo_content');

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

        foreach (['button_url', 'secondary_button_url', 'map_url', 'video_url'] as $urlField) {
            if (! SafeUrl::isSafe($data[$urlField] ?? null)) {
                return back()->withErrors([$urlField => 'Use a relative site path or a valid http/https URL.'])->withInput();
            }
        }
        foreach (['image', 'mobile_image'] as $imageField) {
            if (! SafeUrl::isSafe($data[$imageField] ?? null, true)) {
                return back()->withErrors([$imageField => 'Use a media library path or a valid http/https image URL.'])->withInput();
            }
        }
        if ($error = $this->unavailableMediaPath($data, $section->data ?? [])) {
            return back()->withErrors($error)->withInput();
        }
        if ($page->isPublishedOrScheduled() && $section->type === 'hero' && (! $request->boolean('is_visible') || empty($data['image']))) {
            return back()->withErrors(['publish' => 'A published or scheduled page must keep a visible Hero section with an image. Unpublish the page (or cancel its schedule) before removing or hiding its Hero.'])->withInput();
        }

        $this->recordRevision($page, 'Before editing section');
        $section->update([
            'admin_label' => $request->has('admin_label') ? ($validated['admin_label'] ?? null) : $section->admin_label,
            // The edit form always shows this checkbox, and an unchecked box is simply absent from the request.
            'is_visible' => $request->boolean('is_visible'),
            'data' => $data,
        ]);

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Section saved.');
    }

    public function destroy(Page $page, PageSection $section)
    {
        abort_unless($section->page_id === $page->id, 404);
        if ($page->isPublishedOrScheduled() && $section->type === 'hero') {
            return back()->withErrors(['publish' => 'Unpublish the page (or cancel its schedule) before deleting its Hero section.']);
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

    /**
     * A media/… image path this save introduces must belong to an existing, supported image in the media library,
     * whether it was picked or typed. Paths the section already stores are kept as they are, as are /images/… paths
     * and http(s) URLs, so existing pages are never broken by re-saving them.
     *
     * @return array<string, string>|null the validation error, if any
     */
    private function unavailableMediaPath(array $data, array $stored): ?array
    {
        $known = MediaPicker::imageValues($stored);
        $new = [];
        foreach (['image', 'mobile_image'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if (str_starts_with($value, 'media/') && ! in_array($value, $known, true)) $new[$field][] = $value;
        }
        foreach (MediaPicker::imageValues(['items' => $data['items'] ?? []]) as $value) {
            if (str_starts_with($value, 'media/') && ! in_array($value, $known, true)) $new['items_json'][] = $value;
        }
        if (! $new) return null;

        $available = MediaPicker::selectablePaths(array_merge(...array_values($new)));
        foreach ($new as $field => $paths) {
            if (array_diff($paths, $available)) {
                return [$field => $field === 'items_json'
                    ? 'An item uses a media path that is not an available image in the media library.'
                    : 'This media path is not an available image in the media library. Choose an image from the library.'];
            }
        }

        return null;
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
            'snapshot' => $page->revisionSnapshot(),
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

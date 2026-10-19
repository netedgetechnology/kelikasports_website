<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentItemController extends Controller
{
    private function validType(string $type): string
    {
        abort_unless(in_array($type, ContentItem::TYPES, true), 404);

        return $type;
    }

    public function index(string $type)
    {
        $type = $this->validType($type);
        $items = ContentItem::ofType($type)->orderBy('sort_order')->orderBy('title')->paginate(25);

        return view('admin.content.index', compact('type', 'items'));
    }

    public function create(string $type)
    {
        $type = $this->validType($type);
        $item = new ContentItem(['type' => $type, 'status' => 'draft', 'data' => []]);

        return view('admin.content.form', compact('type', 'item'));
    }

    public function store(Request $request, string $type)
    {
        $type = $this->validType($type);
        $data = $this->validated($request);
        $data['type'] = $type;
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $data['data'] = $this->decodeData($data['data_json'] ?? '');
        unset($data['data_json']);

        ContentItem::create($data);

        return redirect()->route('admin.content.index', $type)->with('success', 'Content item created.');
    }

    public function edit(string $type, ContentItem $item)
    {
        $type = $this->validType($type);
        abort_unless($item->type === $type, 404);

        return view('admin.content.form', compact('type', 'item'));
    }

    public function update(Request $request, string $type, ContentItem $item)
    {
        $type = $this->validType($type);
        abort_unless($item->type === $type, 404);

        $data = $this->validated($request, $item);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['updated_by'] = $request->user()->id;
        $data['data'] = $this->decodeData($data['data_json'] ?? '');
        unset($data['data_json']);
        $item->update($data);

        return back()->with('success', 'Content item saved.');
    }

    public function destroy(string $type, ContentItem $item)
    {
        $type = $this->validType($type);
        abort_unless($item->type === $type, 404);
        $item->delete();

        return back()->with('success', 'Content item archived.');
    }

    private function validated(Request $request, ?ContentItem $item = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => [
                'nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('content_items', 'slug')->where(fn ($query) => $query->where('type', $item?->type ?? $request->route('type')))->ignore($item?->id),
            ],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'data_json' => ['nullable', 'string', 'max:30000'],
        ]);
        $data['sort_order'] = (int) ($request->input('sort_order') ?? 0);

        return $data;
    }

    private function decodeData(string $json): array
    {
        if (trim($json) === '') return [];

        $data = json_decode($json, true);
        if (! is_array($data) || json_last_error() !== JSON_ERROR_NONE || ($data !== [] && array_is_list($data))) {
            throw ValidationException::withMessages(['data_json' => 'Content data must be a valid JSON object.']);
        }

        $this->validateUrls($data);

        return $data;
    }

    private function validateUrls(array $data): void
    {
        $positions = ['center', 'top', 'bottom', 'left', 'right', 'center top', 'center bottom', 'left center', 'right center'];

        foreach ($data as $key => $value) {
            $field = (string) $key;

            if (is_array($value)) {
                $this->validateUrls($value);
                continue;
            }
            if (! is_string($value)) continue;

            if (in_array($field, ['url', 'button_url', 'secondary_button_url', 'map_url', 'registration_url'], true) && ! SafeUrl::isSafe($value)) {
                throw ValidationException::withMessages(['data_json' => "Unsafe URL in field {$field}. Use a relative path or http/https URL."]);
            }
            if (in_array($field, ['image', 'image_path', 'mobile_image'], true) && ! SafeUrl::isSafe($value, true)) {
                throw ValidationException::withMessages(['data_json' => "Unsafe image path in field {$field}. Use the media library path or http/https URL."]);
            }
            if ($field === 'image_position' && ! in_array($value, $positions, true)) {
                throw ValidationException::withMessages(['data_json' => 'Unsupported image focal position.']);
            }
            if ($field === 'color' && ! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                throw ValidationException::withMessages(['data_json' => 'Item colors must be six-digit hex values.']);
            }
        }
    }
}

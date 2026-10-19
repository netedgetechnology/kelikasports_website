<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    public function index()
    {
        $menus = Menu::with(['items.children'])->orderBy('location')->get();

        return view('admin.menus.index', compact('menus'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/', Rule::unique('menus', 'location')],
        ]);

        Menu::create($data + ['is_active' => true]);

        return back()->with('success', 'Menu created.');
    }

    public function addItem(Request $request, Menu $menu)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:2048'],
            'target' => ['required', Rule::in(['_self', '_blank'])],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
        ]);

        if (! SafeUrl::isSafe($data['url'])) {
            return back()->withErrors(['url' => 'Use a relative site path, http/https URL, mailto link, or telephone link.'])->withInput();
        }

        if (isset($data['parent_id'])) {
            abort_unless(
                MenuItem::where('id', $data['parent_id'])->where('menu_id', $menu->id)->whereNull('parent_id')->exists(),
                422,
                'Dropdown items must use a top-level item as their parent.'
            );
        }

        $menu->menuItems()->create($data + [
            'sort_order' => ((int) $menu->menuItems()->max('sort_order')) + 1,
            'is_visible' => true,
        ]);

        return back()->with('success', 'Menu item added.');
    }

    public function updateItem(Request $request, Menu $menu, MenuItem $item)
    {
        abort_unless($item->menu_id === $menu->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:2048'],
            'target' => ['required', Rule::in(['_self', '_blank'])],
            'is_visible' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        if (! SafeUrl::isSafe($data['url'])) {
            return back()->withErrors(['url' => 'Use a relative site path, http/https URL, mailto link, or telephone link.'])->withInput();
        }

        $data['is_visible'] = $request->boolean('is_visible');
        $item->update($data);

        return back()->with('success', 'Menu item saved.');
    }

    public function destroyItem(Menu $menu, MenuItem $item)
    {
        abort_unless($item->menu_id === $menu->id, 404);
        $item->delete();

        return back()->with('success', 'Menu item removed.');
    }
}

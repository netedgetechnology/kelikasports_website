<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\MenuLinks;
use App\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    /** Menu links are stored in a 255-character column. */
    private const URL_MESSAGES = ['url.max' => 'The link must be 255 characters or fewer.'];

    public function index()
    {
        $menus = Menu::with(['items.children.children'])->orderBy('location')->get();
        // Why each link is currently hidden from visitors (draft, archived or missing page…), keyed by item id.
        $links = MenuLinks::load();
        $hiddenLinks = $menus->flatMap(fn ($menu) => $menu->items->flatMap(fn ($item) => [$item, ...$item->children]))
            ->mapWithKeys(fn ($item) => [$item->id => $links->hiddenReason((string) $item->url)])->filter();

        return view('admin.menus.index', compact('menus', 'hiddenLinks'));
    }

    /** Saved-message suffix when the link will not show on the website yet; menu items are never blocked or removed. */
    private static function visibilityNote(string $url): string
    {
        $reason = MenuLinks::load()->hiddenReason($url);

        return $reason ? ' It is hidden on the website for now: '.$reason : '';
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
            'url' => ['required', 'string', 'max:255'],
            'target' => ['required', Rule::in(['_self', '_blank'])],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
        ], self::URL_MESSAGES);

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

        return back()->with('success', 'Menu item added.'.self::visibilityNote($data['url']));
    }

    public function updateItem(Request $request, Menu $menu, MenuItem $item)
    {
        abort_unless($item->menu_id === $menu->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:255'],
            'target' => ['required', Rule::in(['_self', '_blank'])],
            'is_visible' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ], self::URL_MESSAGES);

        if (! SafeUrl::isSafe($data['url'])) {
            return back()->withErrors(['url' => 'Use a relative site path, http/https URL, mailto link, or telephone link.'])->withInput();
        }

        $data['is_visible'] = $request->boolean('is_visible');
        $item->update($data);

        return back()->with('success', 'Menu item saved.'.self::visibilityNote($data['url']));
    }

    /**
     * Removes one item. An item that still has dropdown items is kept: the database would otherwise turn them into
     * top-level items, and the editor cannot move them to another parent, so they are removed one by one first.
     */
    public function destroyItem(Menu $menu, MenuItem $item)
    {
        abort_unless($item->menu_id === $menu->id, 404);

        $children = DB::transaction(function () use ($item) {
            // Locking the item makes a dropdown item added meanwhile wait for, then fail on, the deletion.
            $item = MenuItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $children = MenuItem::where('parent_id', $item->id)->count();
            if ($children === 0) $item->delete();

            return $children;
        });

        if ($children > 0) {
            return back()->withErrors(['menu' => "“{$item->label}” still has {$children} dropdown ".Str::plural('item', $children).'. Remove '.($children === 1 ? 'it' : 'them').' first, then remove this item.']);
        }

        return back()->with('success', "Menu item “{$item->label}” removed.");
    }

    /**
     * Swaps an item with its neighbour among its siblings (the top-level items, or the items of one dropdown) and
     * numbers that group 1, 2, 3… so ties left by hand-typed order numbers cannot make the result ambiguous.
     * Other groups and menus are not touched.
     */
    public function moveItem(Menu $menu, MenuItem $item, string $direction)
    {
        abort_unless($item->menu_id === $menu->id, 404);

        $moved = DB::transaction(function () use ($item, $direction) {
            $siblings = MenuItem::where('menu_id', $item->menu_id)->where('parent_id', $item->parent_id)
                ->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get()->values();
            $index = $siblings->search(fn ($sibling) => $sibling->id === $item->id);
            $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || ! isset($siblings[$swapIndex])) return false;

            [$siblings[$index], $siblings[$swapIndex]] = [$siblings[$swapIndex], $siblings[$index]];
            // Saved one by one so each change clears the cached public menus.
            $siblings->each(fn ($sibling, $position) => $sibling->update(['sort_order' => $position + 1]));

            return true;
        });

        return back()->with('success', $moved ? 'Menu order updated.' : 'That item is already at the '.($direction === 'up' ? 'top' : 'bottom').' of its list.');
    }
}

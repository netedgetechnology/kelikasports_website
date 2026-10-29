<?php
namespace Tests\Feature;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use App\Support\PublicSiteData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
class MenuItemManagementTest extends TestCase
{
    use RefreshDatabase;
    private Menu $main;
    private Menu $footer;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        Page::create(['title' => 'Contact', 'slug' => 'contact', 'status' => 'published', 'published_at' => now()->subDay(), 'show_in_menu' => true]);

        // Main: Alpha, Bravo (dropdown: Kilo, Lima, Mike), Charlie, Delta. Footer: Foxtrot, Golf.
        $this->main = Menu::create(['name' => 'Main', 'location' => 'main', 'is_active' => true]);
        $this->footer = Menu::create(['name' => 'Footer', 'location' => 'footer', 'is_active' => true]);
        foreach (['Alpha', 'Bravo', 'Charlie', 'Delta'] as $i => $label) $this->item($this->main, $label, $i + 1);
        foreach (['Kilo', 'Lima', 'Mike'] as $i => $label) $this->item($this->main, $label, $i + 5, $this->id('Bravo'));
        foreach (['Foxtrot', 'Golf'] as $i => $label) $this->item($this->footer, $label, $i + 1);
        MenuItem::where('label', 'Charlie')->update(['target' => '_blank', 'url' => 'https://example.org/charlie']);
    }
    private function item(Menu $menu, string $label, int $order, ?int $parent = null): MenuItem
    {
        return $menu->menuItems()->create(['label' => $label, 'url' => '/contact#'.strtolower($label), 'target' => '_self', 'sort_order' => $order, 'is_visible' => true, 'parent_id' => $parent]);
    }
    private function id(string $label): int
    {
        return MenuItem::where('label', $label)->value('id');
    }
    private function move(string $label, string $direction, ?Menu $menu = null)
    {
        return $this->from(route('admin.menus.index'))->post(route('admin.menus.items.move', [$menu ?? $this->main, $this->id($label), $direction]));
    }
    private function remove(string $label, ?Menu $menu = null)
    {
        return $this->from(route('admin.menus.index'))->delete(route('admin.menus.items.destroy', [$menu ?? $this->main, $this->id($label)]));
    }
    /** Labels of one sibling group in saved order. */
    private function order(?string $parent = null, ?Menu $menu = null): array
    {
        return MenuItem::where('menu_id', ($menu ?? $this->main)->id)->where('parent_id', $parent ? $this->id($parent) : null)
            ->orderBy('sort_order')->orderBy('id')->pluck('label')->all();
    }
    /** Every column except the order, so a test can show nothing else changed. */
    private function snapshot(): array
    {
        return MenuItem::orderBy('id')->get(['id', 'menu_id', 'parent_id', 'label', 'url', 'target', 'is_visible'])->toArray();
    }
    private function visitor()
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        return $this->get('/contact')->assertOk();
    }

    public function test_an_item_without_dropdown_items_is_removed_and_nothing_else_changes(): void
    {
        $before = collect($this->snapshot())->reject(fn ($row) => $row['label'] === 'Delta')->values()->all();
        $this->remove('Delta')->assertRedirect(route('admin.menus.index'))->assertSessionHas('success', 'Menu item “Delta” removed.');
        $this->assertNull(MenuItem::where('label', 'Delta')->first());
        $this->assertSame($before, $this->snapshot());
        $this->visitor()->assertDontSee('#delta', false)->assertSee('example.org/charlie', false)->assertSee('Foxtrot');
    }
    public function test_removal_asks_for_confirmation_and_is_never_triggered_by_a_link(): void
    {
        $this->get(route('admin.menus.index'))->assertOk()
            ->assertSee('data-confirm="Remove “Delta” from Main? This cannot be undone."', false)
            ->assertSee('<form method="post" action="'.route('admin.menus.items.destroy', [$this->main, $this->id('Delta')]).'">', false);
        // Only a DELETE request removes an item: a followed link or prefetch cannot.
        $this->get('/admin/menus/'.$this->main->id.'/items/'.$this->id('Delta'))->assertClientError();
        $this->post('/admin/menus/'.$this->main->id.'/items/'.$this->id('Delta'))->assertClientError();
        $this->assertSame(9, MenuItem::count());
    }
    public function test_an_item_with_dropdown_items_is_kept_until_they_are_removed(): void
    {
        MenuItem::where('label', 'Mike')->update(['is_visible' => false]); // hidden dropdown items count too
        $html = $this->get(route('admin.menus.index'))->getContent();
        $this->assertStringContainsString('data-remove-blocked="'.$this->id('Bravo').'">To remove this item, first remove its 3 dropdown items.', $html);
        $this->assertStringNotContainsString('Remove “Bravo”', $html);
        $this->assertStringContainsString('Remove “Kilo” from Main?', $html);

        $before = $this->snapshot();
        $this->remove('Bravo')->assertSessionHasErrors(['menu' => '“Bravo” still has 3 dropdown items. Remove them first, then remove this item.']);
        $this->assertSame($before, $this->snapshot(), 'no item was deleted or moved to the top level');

        $this->remove('Kilo')->assertSessionHasNoErrors();
        $this->remove('Lima')->assertSessionHasNoErrors();
        $this->remove('Bravo')->assertSessionHasErrors(['menu' => '“Bravo” still has 1 dropdown item. Remove it first, then remove this item.']);
        $this->remove('Mike')->assertSessionHasNoErrors();
        $this->remove('Bravo')->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(['Alpha', 'Charlie', 'Delta'], $this->order());
        $this->assertSame(0, MenuItem::whereNotNull('parent_id')->count());
    }
    public function test_unknown_items_other_menus_items_and_unknown_directions_are_rejected(): void
    {
        $before = $this->snapshot();
        $this->delete(route('admin.menus.items.destroy', [$this->main, 999999]))->assertNotFound();
        $this->post(route('admin.menus.items.move', [$this->main, 999999, 'up']))->assertNotFound();
        $this->delete(route('admin.menus.items.destroy', [$this->main, $this->id('Golf')]))->assertNotFound();
        $this->post(route('admin.menus.items.move', [$this->main, $this->id('Golf'), 'up']))->assertNotFound();
        $this->delete(route('admin.menus.items.destroy', [999999, $this->id('Alpha')]))->assertNotFound();
        $this->post('/admin/menus/'.$this->main->id.'/items/'.$this->id('Bravo').'/move/sideways')->assertClientError();
        $this->post('/admin/menus/'.$this->main->id.'/items/abc/move/up')->assertClientError();
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(['Foxtrot', 'Golf'], $this->order(null, $this->footer));
    }
    public function test_visitors_cannot_remove_or_move_items(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->remove('Alpha')->assertRedirect(route('login'));
        $this->move('Bravo', 'up')->assertRedirect(route('login'));
        $this->assertSame(['Alpha', 'Bravo', 'Charlie', 'Delta'], $this->order());
    }
    public function test_top_level_items_move_up_and_down(): void
    {
        $this->move('Charlie', 'up')->assertSessionHas('success', 'Menu order updated.');
        $this->assertSame(['Alpha', 'Charlie', 'Bravo', 'Delta'], $this->order());
        $this->move('Alpha', 'down');
        $this->assertSame(['Charlie', 'Alpha', 'Bravo', 'Delta'], $this->order());
        $this->assertSame([1, 2, 3, 4], MenuItem::whereNull('parent_id')->where('menu_id', $this->main->id)->orderBy('sort_order')->pluck('sort_order')->all());
        $this->assertSame(['Kilo', 'Lima', 'Mike'], $this->order('Bravo'), 'the dropdown moves with its parent');
        $this->visitor()->assertSeeInOrder(['example.org/charlie', '#alpha', '#bravo', '#kilo', '#lima', '#mike', '#delta'], false);
    }
    public function test_moving_within_a_dropdown_changes_only_that_dropdown(): void
    {
        $topLevel = MenuItem::whereNull('parent_id')->orderBy('id')->pluck('sort_order', 'label')->all();
        $this->move('Mike', 'up');
        $this->assertSame(['Kilo', 'Mike', 'Lima'], $this->order('Bravo'));
        $this->move('Kilo', 'down');
        $this->assertSame(['Mike', 'Kilo', 'Lima'], $this->order('Bravo'));
        $this->assertSame($topLevel, MenuItem::whereNull('parent_id')->orderBy('id')->pluck('sort_order', 'label')->all(), 'top-level and footer items keep their numbers');
        $this->assertSame(3, MenuItem::where('parent_id', $this->id('Bravo'))->count());
        $this->visitor()->assertSeeInOrder(['#alpha', '#bravo', '#mike', '#kilo', '#lima', 'example.org/charlie'], false);
    }
    public function test_the_ends_of_a_list_stay_put_and_their_buttons_are_disabled(): void
    {
        $orders = MenuItem::orderBy('id')->pluck('sort_order')->all();
        $this->move('Alpha', 'up')->assertSessionHas('success', 'That item is already at the top of its list.');
        $this->move('Mike', 'down')->assertSessionHas('success', 'That item is already at the bottom of its list.');
        $this->assertSame($orders, MenuItem::orderBy('id')->pluck('sort_order')->all());
        $this->get(route('admin.menus.index'))
            ->assertSee('aria-label="Move Alpha up" disabled', false)->assertSee('aria-label="Move Delta down" disabled', false)
            ->assertSee('aria-label="Move Kilo up" disabled', false)->assertSee('aria-label="Move Mike down" disabled', false)
            ->assertDontSee('aria-label="Move Bravo up" disabled', false)->assertDontSee('aria-label="Move Lima down" disabled', false);
    }
    public function test_tied_order_numbers_are_resolved_into_a_clean_sequence(): void
    {
        MenuItem::whereNull('parent_id')->where('menu_id', $this->main->id)->update(['sort_order' => 7]); // ties fall back to id order
        $this->move('Charlie', 'up');
        $this->assertSame(['Alpha', 'Charlie', 'Bravo', 'Delta'], $this->order());
        $this->assertSame([1, 2, 3, 4], MenuItem::whereNull('parent_id')->where('menu_id', $this->main->id)->orderBy('sort_order')->pluck('sort_order')->all());
        $this->assertSame([5, 6, 7], MenuItem::where('parent_id', $this->id('Bravo'))->orderBy('id')->pluck('sort_order')->all());
    }
    public function test_moving_keeps_visibility_links_targets_and_parents(): void
    {
        MenuItem::where('label', 'Lima')->update(['is_visible' => false]);
        $before = $this->snapshot();
        $this->move('Charlie', 'up');
        $this->move('Lima', 'up');
        $this->move('Golf', 'up', $this->footer);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(['Golf', 'Foxtrot'], $this->order(null, $this->footer));
        $this->visitor()->assertDontSee('#lima', false)->assertSee('href="https://example.org/charlie" target="_blank"', false);
    }
    public function test_each_change_clears_the_cached_public_menus(): void
    {
        $this->visitor()->assertSeeInOrder(['#alpha', '#bravo'], false);
        $this->assertTrue(Cache::has(PublicSiteData::CACHE_KEY));
        $this->actingAs(User::first());
        $this->move('Bravo', 'up');
        $this->assertFalse(Cache::has(PublicSiteData::CACHE_KEY));
        $this->visitor()->assertSeeInOrder(['#bravo', '#alpha'], false);

        $this->assertTrue(Cache::has(PublicSiteData::CACHE_KEY));
        $this->actingAs(User::first());
        $this->remove('Delta');
        $this->assertFalse(Cache::has(PublicSiteData::CACHE_KEY));
        $this->visitor()->assertDontSee('#delta', false);
    }
    public function test_a_failure_part_way_through_a_move_leaves_the_order_unchanged(): void
    {
        $orders = MenuItem::orderBy('id')->pluck('sort_order', 'label')->all();
        $alpha = $this->id('Alpha');
        // Bravo is renumbered first, then saving Alpha fails.
        Event::listen('eloquent.updating: '.MenuItem::class, function (MenuItem $item) use ($alpha) {
            if ($item->id === $alpha) throw new \RuntimeException('Simulated database failure');
        });
        $this->move('Bravo', 'up')->assertStatus(500);
        $this->assertSame($orders, MenuItem::orderBy('id')->pluck('sort_order', 'label')->all());
    }
}

<?php
namespace Tests\Feature;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class MenuLinksTest extends TestCase
{
    use RefreshDatabase;
    private Menu $main;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        foreach (['home', 'about', 'contact'] as $slug) $this->page($slug);
        $this->page('camp', ['status' => 'draft', 'published_at' => null]);
        $this->page('old-news')->delete();
        $this->page('members', ['show_in_menu' => false]);
        $this->page('gala', ['published_at' => now()->addDays(3)]);
        Redirect::create(['from_path' => '/team', 'to_path' => '/about', 'status_code' => 301, 'is_active' => true]);
        Redirect::create(['from_path' => '/summer', 'to_path' => '/camp', 'status_code' => 301, 'is_active' => true]);

        $this->main = Menu::create(['name' => 'Main', 'location' => 'main', 'is_active' => true]);
        $links = [
            ['Home link', '/'], ['About link', '/about'], ['Draft camp link', '/camp'], ['Archived news link', '/old-news'],
            ['Missing page link', '/ghost'], ['Members link', '/members'], ['Gala link', '/gala'],
            ['Partner site', 'https://example.org/partner'], ['Email us', 'mailto:hello@example.org'], ['Search link', '/search?q=badminton'],
            ['Anchor link', '#contact-form'], ['Nested custom', '/programs/kids'], ['Team via redirect', '/team'], ['Summer via redirect', '/summer'],
            ['Contact section', '/contact#form'],
        ];
        foreach ($links as $i => [$label, $url]) $this->item($label, $url, $i + 1);
        $about = MenuItem::where('label', 'About link')->first();
        $this->item('Camp dropdown', '/camp', 1, $about->id);
        $this->item('About team anchor', '/about#team', 2, $about->id);
    }
    private function page(string $slug, array $attributes = []): Page
    {
        return Page::create($attributes + ['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subDay(), 'show_in_menu' => true]);
    }
    private function item(string $label, string $url, int $order, ?int $parent = null): MenuItem
    {
        return $this->main->menuItems()->create(['label' => $label, 'url' => $url, 'target' => '_self', 'sort_order' => $order, 'is_visible' => true, 'parent_id' => $parent]);
    }
    private function visitor()
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        return $this->get('/contact')->assertOk();
    }
    private const SHOWN = ['Home link', 'About link', 'Partner site', 'Email us', 'Search link', 'Anchor link', 'Nested custom', 'Team via redirect', 'Contact section'];
    private const HIDDEN = ['Draft camp link', 'Archived news link', 'Missing page link', 'Members link', 'Gala link', 'Summer via redirect'];

    public function test_menus_show_live_pages_and_custom_links_in_order_and_hide_links_to_pages_that_are_not_live(): void
    {
        $response = $this->visitor()->assertSeeInOrder(self::SHOWN);
        foreach (self::HIDDEN as $label) $response->assertDontSee($label);
        foreach (['href="/camp"', 'href="/old-news"', 'href="/ghost"', 'href="/summer"'] as $broken) $response->assertDontSee($broken, false);
        $response->assertSee('href="https://example.org/partner"', false)->assertSee('href="mailto:hello@example.org"', false);
        // Dropdown items follow the same rule.
        $response->assertSee('About team anchor')->assertDontSee('Camp dropdown');
    }
    public function test_menu_items_are_kept_unchanged_so_administrators_can_fix_them(): void
    {
        $before = MenuItem::orderBy('id')->get(['id', 'label', 'url', 'sort_order', 'is_visible', 'parent_id'])->toArray();
        $this->visitor();
        $this->assertSame($before, MenuItem::orderBy('id')->get(['id', 'label', 'url', 'sort_order', 'is_visible', 'parent_id'])->toArray());
    }
    public function test_the_menu_editor_flags_each_hidden_link_with_the_reason(): void
    {
        $html = $this->get(route('admin.menus.index'))->assertOk()->getContent();
        $flag = fn (string $label) => preg_match('/value="'.preg_quote($label, '/').'".*?<div class="alert alert-warning[^"]*"[^>]*data-hidden-link="\d+"><strong>Hidden on the website:<\/strong> ([^<]+)<\/div><div class="d-flex[^"]*">/s', $html, $m) ? $m[1] : null;
        $this->assertStringContainsString('the page at /camp is a draft', $flag('Draft camp link'));
        $this->assertStringContainsString('the page at /old-news is archived', $flag('Archived news link'));
        $this->assertStringContainsString('no page exists at /ghost', $flag('Missing page link'));
        $this->assertStringContainsString('set to stay out of menus', $flag('Members link'));
        $this->assertStringContainsString('scheduled for', $flag('Gala link'));
        $this->assertStringContainsString('redirects there from /summer', $flag('Summer via redirect'));
        $this->assertSame(count(self::HIDDEN) + 1, substr_count($html, 'data-hidden-link='), 'only hidden links (plus the camp dropdown item) are flagged');
    }
    public function test_saving_a_link_to_a_page_that_is_not_live_is_allowed_but_explained(): void
    {
        $this->post(route('admin.menus.items.store', $this->main), ['label' => 'New camp link', 'url' => '/camp', 'target' => '_self'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'Menu item added. It is hidden on the website for now: the page at /camp is a draft'));
        $this->assertDatabaseHas('menu_items', ['label' => 'New camp link', 'url' => '/camp']);
        $this->post(route('admin.menus.items.store', $this->main), ['label' => 'Another about link', 'url' => '/about', 'target' => '_self'])->assertSessionHas('success', 'Menu item added.');
        $this->post(route('admin.menus.items.store', $this->main), ['label' => 'External', 'url' => 'https://example.org', 'target' => '_blank'])->assertSessionHas('success', 'Menu item added.');
    }
    public function test_publishing_unpublishing_and_renaming_a_page_update_the_menu(): void
    {
        $camp = Page::where('slug', 'camp')->first();
        PageSection::create(['page_id' => $camp->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => ['image' => 'https://images.unsplash.com/photo-1?w=1800']]);
        $this->visitor()->assertDontSee('Draft camp link'); // cached now
        $this->actingAs(User::first())->post(route('admin.pages.publish', $camp))->assertSessionHasNoErrors();
        $this->visitor()->assertSee('Draft camp link')->assertSee('Summer via redirect')->assertSee('Camp dropdown');
        $this->travel(1)->minutes(); // renaming adds a redirect only for a page that was already live
        $this->actingAs(User::first())->put(route('admin.pages.update', $camp), ['title' => 'Camp', 'slug' => 'summer-camp', 'template' => 'standard', 'status' => 'published', 'robots_directive' => 'index,follow', 'show_in_menu' => 1])->assertSessionHasNoErrors();
        $this->visitor()->assertSee('Draft camp link'); // /camp now redirects to the renamed, still-live page
        $this->actingAs(User::first())->post(route('admin.pages.unpublish', $camp))->assertSessionHasNoErrors();
        $this->visitor()->assertDontSee('Draft camp link')->assertDontSee('Camp dropdown');
    }
    public function test_archiving_hides_a_link_and_restoring_and_republishing_brings_it_back(): void
    {
        $this->visitor()->assertSee('About link');
        $about = Page::where('slug', 'about')->first();
        $this->actingAs(User::first())->delete(route('admin.pages.destroy', $about))->assertSessionHasNoErrors();
        // The page, its dropdown and the redirect that leads to it all disappear from the menu.
        $this->visitor()->assertDontSee('About link')->assertDontSee('About team anchor')->assertDontSee('Team via redirect');
        $this->actingAs(User::first())->post(route('admin.pages.restore', $about))->assertSessionHasNoErrors();
        $this->visitor()->assertDontSee('About link'); // restored as a draft: still hidden until published
        Page::where('slug', 'about')->first()->update(['status' => 'published']);
        $this->visitor()->assertSee('About link')->assertSee('About team anchor')->assertSee('Team via redirect');
    }
    public function test_adding_a_redirect_updates_menus_that_link_to_its_address(): void
    {
        $this->visitor()->assertDontSee('Missing page link');
        $this->actingAs(User::first())->post(route('admin.redirects.store'), ['from_path' => '/ghost', 'to_path' => '/about', 'status_code' => 301])->assertSessionHasNoErrors();
        $this->visitor()->assertSee('Missing page link');
    }
    public function test_cms_preview_still_shows_links_to_draft_pages(): void
    {
        $camp = Page::where('slug', 'camp')->first();
        $this->get(route('admin.pages.preview', $camp))->assertOk()->assertSee('Draft camp link')->assertDontSee('Archived news link')->assertDontSee('Missing page link');
    }
    public function test_resolving_links_takes_a_fixed_number_of_queries_however_many_items_there_are(): void
    {
        $queries = function () {
            \Illuminate\Support\Facades\Cache::flush();
            DB::flushQueryLog(); DB::enableQueryLog(); $this->visitor(); DB::disableQueryLog();
            return count(array_filter(array_column(DB::getQueryLog(), 'query'), fn ($q) => str_contains($q, 'from "pages"') || str_contains($q, 'from "redirects"')));
        };
        $before = $queries();
        foreach (range(1, 30) as $i) $this->item("Extra $i", $i % 2 ? "/missing-$i" : '/about', 100 + $i);
        $this->assertSame($before, $queries());
    }
}

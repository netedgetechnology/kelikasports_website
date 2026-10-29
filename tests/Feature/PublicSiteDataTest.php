<?php
namespace Tests\Feature;
use App\Models\ContentItem;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use App\Support\PublicSiteData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class PublicSiteDataTest extends TestCase
{
    use RefreshDatabase;
    private Menu $main;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (['about', 'contact'] as $slug) $this->page($slug);
        $this->main = Menu::create(['name' => 'Main', 'location' => 'main', 'is_active' => true]);
        $this->main->menuItems()->create(['label' => 'About us', 'url' => '/about', 'target' => '_self', 'sort_order' => 1, 'is_visible' => true]);
        $footer = Menu::create(['name' => 'Footer', 'location' => 'footer', 'is_active' => true]);
        $footer->menuItems()->create(['label' => 'Get in touch', 'url' => '/contact', 'target' => '_self', 'sort_order' => 1, 'is_visible' => true]);
        ContentItem::create(['type' => 'sport', 'title' => 'Badminton', 'slug' => 'badminton', 'status' => 'published', 'sort_order' => 1]);
        Setting::write('site_tagline', 'Original tagline', 'general');
    }
    private function page(string $slug, array $attributes = []): Page
    {
        return Page::create($attributes + ['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subDay()]);
    }
    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'admin@example.test'], ['name' => 'Admin', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]);
    }
    /** Runs a request and returns the SQL it sent. */
    private function queriesDuring(callable $request): array
    {
        DB::flushQueryLog(); DB::enableQueryLog(); $request(); DB::disableQueryLog();
        return array_column(DB::getQueryLog(), 'query');
    }
    private function countMatching(array $queries, string $needle): int
    {
        return count(array_filter($queries, fn ($sql) => str_contains($sql, $needle)));
    }

    // Data availability
    public function test_every_public_view_gets_settings_menus_and_footer_sports(): void
    {
        foreach (['/about', '/search?q=bad', '/no/such/page'] as $url) {
            $this->get($url)->assertSee('About us')->assertSee('Get in touch')->assertSee('Badminton')->assertSee('--kelika-green:'.Branding::DEFAULTS['primary_color'], false);
        }
    }
    public function test_shared_data_is_built_once_per_request_and_then_served_from_the_cache(): void
    {
        $cold = $this->queriesDuring(fn () => $this->get('/about')->assertOk());
        $this->assertSame(1, $this->countMatching($cold, 'from "settings"'), 'settings loaded once, not once per view');
        $this->assertSame(1, $this->countMatching($cold, 'from "menus"'));
        $warm = $this->queriesDuring(fn () => $this->get('/contact')->assertOk());
        $this->assertSame(0, $this->countMatching($warm, 'from "settings"') + $this->countMatching($warm, 'from "menus"') + $this->countMatching($warm, 'from "menu_items"'));
    }
    public function test_sections_reuse_their_page_instead_of_querying_it_again(): void
    {
        $home = $this->page('home');
        foreach (range(1, 3) as $i) PageSection::create(['page_id' => $home->id, 'type' => 'cta', 'sort_order' => $i, 'data' => ['title' => "Call $i"]]);
        $queries = $this->queriesDuring(fn () => $this->get('/')->assertOk()->assertSee('Call 3'));
        $this->assertSame(0, $this->countMatching($queries, 'from "pages" where "pages"."id" = ?'));
    }

    // Cache invalidation after CMS changes
    public function test_saving_settings_in_the_cms_updates_the_public_site(): void
    {
        $this->get('/about')->assertSee('Original tagline');
        $settings = ['site_name' => 'KELIKA', 'site_tagline' => 'Fresh tagline', 'booking_button_label' => 'Book a Court', 'font_family' => 'Georgia', 'button_style' => 'soft'];
        foreach (Branding::DEFAULTS as $key => $value) if (str_ends_with($key, '_color')) $settings[$key] = $value;
        $this->actingAs($this->admin())->put(route('admin.settings.update'), ['primary_color' => '#123456'] + $settings)->assertSessionHasNoErrors();
        $this->get('/about')->assertSee('Fresh tagline')->assertSee('--kelika-green:#123456', false)->assertSee("--kelika-font:'Georgia'", false);
    }
    public function test_menu_changes_in_the_cms_update_the_navigation(): void
    {
        $this->get('/about')->assertSee('About us');
        $this->actingAs($this->admin());
        $this->post(route('admin.menus.items.store', $this->main), ['label' => 'Programs for kids', 'url' => '/contact', 'target' => '_self'])->assertSessionHasNoErrors();
        $this->get('/about')->assertSee('Programs for kids');
        $item = MenuItem::where('label', 'About us')->first();
        $this->put(route('admin.menus.items.update', [$this->main, $item]), ['label' => 'Our story', 'url' => '/about', 'target' => '_self', 'sort_order' => 1, 'is_visible' => 1])->assertSessionHasNoErrors();
        $this->get('/contact')->assertSee('Our story')->assertDontSee('About us');
        $this->delete(route('admin.menus.items.destroy', [$this->main, $item]));
        $this->get('/contact')->assertDontSee('Our story');
    }
    public function test_page_and_content_changes_in_the_cms_update_menus_and_footer(): void
    {
        $this->get('/contact')->assertSee('About us')->assertSee('Badminton');
        $this->actingAs($this->admin());
        $about = Page::where('slug', 'about')->first();
        $this->put(route('admin.pages.update', $about), ['title' => 'About', 'slug' => 'about', 'template' => 'standard', 'status' => 'published', 'robots_directive' => 'index,follow'])->assertSessionHasNoErrors(); // show_in_menu unchecked
        $this->get('/contact')->assertDontSee('About us');
        $sport = ContentItem::where('slug', 'badminton')->first();
        $this->put(route('admin.content.update', ['sport', $sport]), ['title' => 'Pickleball', 'slug' => 'badminton', 'status' => 'published'])->assertSessionHasNoErrors();
        $this->get('/contact')->assertSee('Pickleball');
        $this->delete(route('admin.content.destroy', ['sport', $sport]));
        $this->get('/contact')->assertDontSee('Pickleball');
    }
    public function test_cache_never_outlives_a_scheduled_page_publication(): void
    {
        $this->page('camp', ['published_at' => now()->addSeconds(120)]);
        $this->get('/about');
        $this->assertTrue(Cache::has(PublicSiteData::CACHE_KEY));
        $this->travel(121)->seconds();
        $this->assertFalse(Cache::has(PublicSiteData::CACHE_KEY));
    }
    public function test_data_cached_by_another_request_during_a_transaction_is_cleared_on_commit(): void
    {
        DB::transaction(function () {
            Setting::write('site_tagline', 'Committed tagline', 'general');
            Cache::put(PublicSiteData::CACHE_KEY, ['stale' => true]); // a concurrent visitor re-caching old rows
        });
        $this->assertFalse(Cache::has(PublicSiteData::CACHE_KEY));
    }

    // Preview is per-user and must never be cached for visitors
    public function test_cms_preview_shows_drafts_without_caching_them_for_visitors(): void
    {
        ContentItem::create(['type' => 'sport', 'title' => 'Draft Squash', 'slug' => 'draft-squash', 'status' => 'draft', 'sort_order' => 2]);
        $draft = $this->page('summer-camp', ['status' => 'draft', 'published_at' => null]);
        $this->actingAs($this->admin())->get(route('admin.pages.preview', $draft))->assertOk()->assertSee('Draft Squash');
        $this->assertFalse(Cache::has(PublicSiteData::CACHE_KEY));
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get('/about')->assertDontSee('Draft Squash');
        $this->assertTrue(Cache::has(PublicSiteData::CACHE_KEY));
    }
}

<?php
namespace Tests\Feature;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
class RoutingCorrectnessTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
    }
    private function page(string $slug): Page
    {
        return Page::create(['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subDay()]);
    }
    private function rename(Page $page, string $slug)
    {
        return $this->put(route('admin.pages.update', $page), ['title' => $page->title, 'slug' => $slug, 'template' => 'standard', 'status' => 'published', 'robots_directive' => 'index,follow']);
    }
    private function storeRedirect(string $from, string $to)
    {
        return $this->from(route('admin.redirects.index'))->post(route('admin.redirects.store'), ['from_path' => $from, 'to_path' => $to, 'status_code' => 301]);
    }

    // 1. Slug changes and redirect loops
    public function test_renaming_a_live_page_redirects_its_old_url(): void
    {
        $page = $this->page('about');
        $this->rename($page, 'about-us')->assertSessionHasNoErrors();
        $this->get('/about')->assertRedirect('/about-us')->assertStatus(301);
        $this->get('/about-us')->assertOk();
    }
    public function test_renaming_a_page_back_does_not_create_a_loop(): void
    {
        $page = $this->page('about');
        $this->rename($page, 'about-us');
        $this->rename($page->fresh(), 'about')->assertSessionHasNoErrors();
        $this->get('/about')->assertOk();
        $this->get('/about-us')->assertRedirect('/about');
        $this->assertDatabaseHas('redirects', ['from_path' => '/about', 'is_active' => false]);
        $this->assertDatabaseMissing('redirects', ['from_path' => '/about-us', 'to_path' => '/about-us']);
    }
    public function test_repeated_renames_point_every_old_url_straight_at_the_current_one(): void
    {
        $page = $this->page('a');
        $this->rename($page, 'b');
        $this->rename($page->fresh(), 'c');
        $this->get('/a')->assertRedirect('/c');
        $this->get('/b')->assertRedirect('/c');
        // Moving back to an earlier slug again must still leave no loop and no chain.
        $this->rename($page->fresh(), 'a');
        $this->get('/a')->assertOk();
        $this->get('/b')->assertRedirect('/a');
        $this->get('/c')->assertRedirect('/a');
    }
    public function test_a_live_page_wins_over_a_stale_redirect_from_its_path(): void
    {
        $this->page('about');
        Redirect::create(['from_path' => '/about', 'to_path' => '/contact', 'status_code' => 301, 'is_active' => true]);
        $this->get('/about')->assertOk();
    }
    public function test_a_redirect_pointing_back_at_its_own_path_is_never_followed(): void
    {
        // Stored directly, bypassing validation, to prove the public site cannot loop on bad data.
        Redirect::create(['from_path' => '/old-page', 'to_path' => '/Old-Page/', 'status_code' => 301, 'is_active' => true]);
        $this->get('/old-page')->assertNotFound();
    }
    public function test_admin_cannot_create_a_redirect_loop(): void
    {
        $this->storeRedirect('/x', '/y')->assertSessionHasNoErrors();
        $this->storeRedirect('/y', '/z')->assertSessionHasNoErrors();
        $this->storeRedirect('/z', '/x')->assertSessionHasErrors(['to_path' => 'This redirect would create a loop: /z → /x → /y → /z.']);
        $this->storeRedirect('/same', '/Same/')->assertSessionHasErrors(['to_path' => 'The source and destination cannot be the same path.']);
        // Editing a redirect is checked against the others, not against its own old destination.
        $x = Redirect::where('from_path', '/x')->first();
        $this->put(route('admin.redirects.update', $x), ['from_path' => '/x', 'to_path' => '/elsewhere', 'status_code' => 302, 'is_active' => 1])->assertSessionHasNoErrors();
    }

    // 2. Sources the catch-all route cannot match
    public function test_nested_uppercase_and_legacy_file_paths_are_redirected(): void
    {
        $this->storeRedirect('/Old-Site/Programs.html/', '/programs')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('redirects', ['from_path' => '/old-site/programs.html']);
        $this->get('/Old-Site/Programs.html')->assertRedirect('/programs')->assertStatus(301);
        $this->get('/old-site/programs.html')->assertRedirect('/programs');
        $this->storeRedirect('/News', 'https://example.com/news')->assertSessionHasNoErrors();
        $this->get('/NEWS')->assertRedirect('https://example.com/news');
    }
    public function test_unknown_paths_still_return_not_found(): void
    {
        $this->get('/nothing/here')->assertNotFound();
        $this->get('/Nothing')->assertNotFound();
    }
    public static function invalidSources(): array
    {
        return [
            'site root' => ['/'], 'reserved build folder' => ['/build/app.css'], 'reserved storage' => ['/storage/media/x.jpg'],
            'admin area' => ['/admin/old'], 'health check' => ['/up'], 'search route' => ['/search'], 'live page path' => ['/about'],
            'space' => ['/a b'], 'dotfile' => ['/.env'], 'dot segment' => ['/old/../secret'], 'query string' => ['/old?page=1'],
            'longer than the column' => ['/'.str_repeat('a', 260)],
        ];
    }
    #[DataProvider('invalidSources')]
    public function test_redirect_sources_the_site_cannot_serve_are_rejected(string $from): void
    {
        $this->page('about');
        $this->storeRedirect($from, '/contact')->assertSessionHasErrors('from_path');
        $this->assertSame(0, Redirect::count());
    }

    // 3. Reserved page slugs
    public static function reservedSlugs(): array
    {
        return array_map(fn ($slug) => [$slug], ['up' => 'up', 'build' => 'build', 'css' => 'css', 'images' => 'images', 'js' => 'js', 'storage' => 'storage', 'admin' => 'admin', 'search' => 'search']);
    }
    #[DataProvider('reservedSlugs')]
    public function test_reserved_slugs_cannot_be_used_for_pages(string $slug): void
    {
        $this->post(route('admin.pages.store'), ['title' => 'Test', 'slug' => $slug, 'template' => 'standard', 'status' => 'draft', 'robots_directive' => 'index,follow'])->assertSessionHasErrors('slug');
        $this->rename($this->page('about'), $slug)->assertSessionHasErrors('slug');
        $this->assertDatabaseMissing('pages', ['slug' => $slug]);
    }
    public function test_ordinary_slugs_are_still_accepted_and_the_health_route_still_works(): void
    {
        $this->post(route('admin.pages.store'), ['title' => 'Junior Camp', 'slug' => 'junior-camp', 'template' => 'standard', 'status' => 'draft', 'robots_directive' => 'index,follow'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pages', ['slug' => 'junior-camp']);
        $this->get('/up')->assertOk();
    }
}

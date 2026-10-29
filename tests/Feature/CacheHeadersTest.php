<?php
namespace Tests\Feature;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/**
 * Apache gives long-lived "public, immutable" caching only to static files under public/build/assets. Everything
 * Laravel renders (pages, admin, CMS preview) must stay private so no shared cache can store it.
 */
class CacheHeadersTest extends TestCase
{
    use RefreshDatabase;
    private function assertPrivate($response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
        $this->assertStringNotContainsString('immutable', $cacheControl);
    }
    public function test_laravel_responses_are_never_publicly_cacheable(): void
    {
        $this->withoutVite();
        $page = Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'published_at' => now()->subDay()]);
        $draft = Page::create(['title' => 'Camp', 'slug' => 'camp', 'status' => 'draft']);
        $this->assertPrivate($this->get('/about')->assertOk());
        $this->assertPrivate($this->get('/no/such/page')->assertNotFound());
        $this->assertPrivate($this->get('/admin/login')->assertOk());
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        $this->assertPrivate($this->get(route('admin.dashboard'))->assertOk());
        $this->assertPrivate($this->get(route('admin.pages.preview', $draft))->assertOk());
    }
    public function test_every_laravel_response_carries_the_baseline_security_headers(): void
    {
        $this->withoutVite();
        Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'published_at' => now()->subDay()]);
        $expected = ['X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'SAMEORIGIN', 'Referrer-Policy' => 'strict-origin-when-cross-origin'];
        $responses = [$this->get('/about')->assertOk(), $this->get('/no/such/page')->assertNotFound(), $this->get('/sitemap.xml')->assertOk(), $this->get('/admin/login')->assertOk(), $this->get('/admin')->assertRedirect()];
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        $responses[] = $this->get(route('admin.dashboard'))->assertOk();
        $responses[] = $this->get(route('admin.enquiries.export'))->assertOk();
        foreach ($responses as $response) {
            foreach ($expected as $name => $value) $this->assertSame($value, $response->headers->get($name), $name);
        }
        // A response that sets its own value keeps it.
        \Illuminate\Support\Facades\Route::get('/__framed-test', fn () => response('ok')->header('X-Frame-Options', 'DENY'));
        $this->get('/__framed-test')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}

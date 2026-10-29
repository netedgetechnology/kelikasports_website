<?php
namespace Tests\Feature;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
class NotFoundSessionTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // phpunit.xml uses array sessions; switch to real database sessions here so rows can be counted.
        config(['session.driver' => 'database']);
        Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'published_at' => now()->subDay()]);
    }
    private function cookieName(): string
    {
        return config('session.cookie');
    }
    public static function unknownUrls(): array
    {
        return ['nested path (fallback route)' => ['/old-site/missing.html'], 'uppercase path (fallback route)' => ['/Missing'], 'single segment (page route)' => ['/no-such-page']];
    }
    #[DataProvider('unknownUrls')]
    public function test_unknown_urls_show_the_branded_404_without_creating_a_session(string $url): void
    {
        $this->get($url)->assertNotFound()->assertSee('404 · NOT FOUND', false)->assertSee('--kelika-green:#064f43', false)->assertCookieMissing($this->cookieName());
        $this->assertSame(0, DB::table('sessions')->count());
    }
    public function test_valid_pages_still_start_and_store_a_session(): void
    {
        $this->get('/about')->assertOk()->assertCookie($this->cookieName());
        $this->assertSame(1, DB::table('sessions')->count());
    }
    public function test_a_visitor_with_a_session_keeps_it_on_a_404(): void
    {
        $this->get('/about');
        $id = DB::table('sessions')->value('id');
        $this->withCookie($this->cookieName(), $id)->get('/no-such-page')->assertNotFound()->assertCookie($this->cookieName());
        $this->assertSame([$id], DB::table('sessions')->pluck('id')->all());
    }
}

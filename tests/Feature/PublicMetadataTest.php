<?php
namespace Tests\Feature;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
/** The public <head> (canonical, Open Graph, Twitter/X card, robots), the sitemap and robots.txt. */
class PublicMetadataTest extends TestCase
{
    use RefreshDatabase;
    private const SITE = 'https://www.kelika.test';
    private string $root;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->root = sys_get_temp_dir().'/kelika-meta-test-'.bin2hex(random_bytes(6));
        mkdir("{$this->root}/public", 0755, true);
        config(['app.url' => self::SITE, 'filesystems.disks.public.root' => "{$this->root}/public", 'filesystems.disks.public.url' => self::SITE.'/storage']);
        Storage::forgetDisk('public');
        Setting::write('site_name', 'KELIKA');
        Setting::write('site_tagline', 'Play. Grow. Belong.');
        $this->page('home', 'Home');
        $this->page('about', 'About us');
    }
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
        parent::tearDown();
    }
    private function page(string $slug, string $title, array $attributes = []): Page
    {
        $page = Page::create($attributes + ['title' => $title, 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'show_in_menu' => true, 'template' => 'standard']);
        PageSection::create(['page_id' => $page->id, 'type' => 'rich_text', 'sort_order' => 1, 'is_visible' => true, 'data' => ['title' => $title, 'body' => 'Body']]);
        return $page;
    }
    private function image(string $name = 'aa000001-0000-4000-8000-000000000001.jpg', string $mime = 'image/jpeg', bool $withFile = true): MediaAsset
    {
        $path = 'media/2026/10/'.$name;
        if ($withFile) Storage::disk('public')->put($path, 'x');
        return MediaAsset::create(['disk' => 'public', 'path' => $path, 'original_name' => $name, 'mime_type' => $mime, 'size_bytes' => 1]);
    }
    /** The <head> of a public URL, as name => content (meta) plus "canonical" and "title". */
    private function metaOf(string $uri): array
    {
        $html = $this->get($uri)->getContent();
        $head = substr($html, 0, (int) strpos($html, '</head>'));
        preg_match_all('/<meta (?:name|property)="([^"]+)" content="([^"]*)">/', $head, $m);
        $meta = array_combine($m[1], array_map(fn ($v) => html_entity_decode($v, ENT_QUOTES), $m[2]));
        if (preg_match('/<link rel="canonical" href="([^"]+)">/', $head, $c)) $meta['canonical'] = html_entity_decode($c[1]);
        preg_match('/<title>\s*(.*?)\s*<\/title>/s', $head, $t);
        $meta['title'] = html_entity_decode($t[1] ?? '');
        return $meta;
    }

    public function test_a_page_without_overrides_falls_back_to_its_title_the_tagline_and_its_own_address(): void
    {
        $meta = $this->metaOf('/about');
        $this->assertSame('About us', $meta['title']);
        $this->assertSame(self::SITE.'/about', $meta['canonical']);
        $this->assertSame(self::SITE.'/about', $meta['og:url']);
        $this->assertSame(['About us', 'Play. Grow. Belong.', 'website', 'KELIKA'], [$meta['og:title'], $meta['og:description'], $meta['og:type'], $meta['og:site_name']]);
        $this->assertSame(['summary', 'About us', 'Play. Grow. Belong.'], [$meta['twitter:card'], $meta['twitter:title'], $meta['twitter:description']]);
        $this->assertArrayNotHasKey('og:image', $meta, 'no sharing image is invented');
        $this->assertArrayNotHasKey('twitter:image', $meta);
        $this->assertSame(self::SITE.'/', $this->metaOf('/')['canonical']);
    }
    public function test_seo_and_open_graph_overrides_are_kept_in_order(): void
    {
        Page::where('slug', 'about')->update(['seo_title' => 'About KELIKA Academy', 'seo_description' => 'Who we are']);
        $meta = $this->metaOf('/about');
        $this->assertSame(['About KELIKA Academy', 'Who we are', 'About KELIKA Academy', 'Who we are', 'Who we are'], [$meta['title'], $meta['description'], $meta['og:title'], $meta['og:description'], $meta['twitter:description']]);
        Page::where('slug', 'about')->update(['og_title' => 'Meet the team', 'og_description' => 'Coaches & athletes']);
        $meta = $this->metaOf('/about');
        $this->assertSame(['About KELIKA Academy', 'Meet the team', 'Coaches & athletes', 'Meet the team'], [$meta['title'], $meta['og:title'], $meta['og:description'], $meta['twitter:title']]);
    }
    public function test_a_configured_canonical_url_is_used_as_given_even_when_external(): void
    {
        Page::where('slug', 'about')->update(['canonical_url' => 'https://partner.example/about-kelika']);
        $meta = $this->metaOf('/about');
        $this->assertSame('https://partner.example/about-kelika', $meta['canonical']);
        $this->assertSame('https://partner.example/about-kelika', $meta['og:url']);
    }
    public function test_urls_use_the_configured_site_address_never_the_request_host(): void
    {
        $meta = $this->metaOf('http://attacker.example/about');
        $this->assertSame(self::SITE.'/about', $meta['canonical']);
        $this->assertStringNotContainsString('attacker.example', $this->get('http://attacker.example/sitemap.xml')->getContent());
    }
    public function test_a_page_sharing_image_is_absolute_and_used_for_both_cards(): void
    {
        $asset = $this->image();
        Page::where('slug', 'about')->update(['og_media_id' => $asset->id]);
        $meta = $this->metaOf('/about');
        $this->assertSame(self::SITE.'/storage/'.$asset->path, $meta['og:image']);
        $this->assertSame($meta['og:image'], $meta['twitter:image']);
        $this->assertSame('summary_large_image', $meta['twitter:card']);
    }
    public function test_the_global_sharing_image_is_made_absolute_whatever_form_it_is_stored_in(): void
    {
        $asset = $this->image('bb000001-0000-4000-8000-000000000001.png', 'image/png');
        foreach (['/images/share.jpg' => self::SITE.'/images/share.jpg', $asset->path => self::SITE.'/storage/'.$asset->path, 'https://cdn.example/share.jpg' => 'https://cdn.example/share.jpg'] as $stored => $expected) {
            Setting::write('social_image_url', $stored, 'branding');
            $this->assertSame($expected, $this->metaOf('/about')['og:image'], $stored);
        }
        Setting::write('social_image_url', '', 'branding');
        $this->assertArrayNotHasKey('og:image', $this->metaOf('/about'));
    }
    public function test_an_unavailable_page_image_falls_back_to_the_global_image_or_none(): void
    {
        $asset = $this->image();
        Page::where('slug', 'about')->update(['og_media_id' => $asset->id]);
        Storage::disk('public')->delete($asset->path);
        $this->assertArrayNotHasKey('og:image', $this->metaOf('/about'), 'a missing file is never advertised');
        Setting::write('social_image_url', '/images/share.jpg', 'branding');
        $this->assertSame(self::SITE.'/images/share.jpg', $this->metaOf('/about')['og:image']);
    }
    public function test_media_trash_and_restore_change_the_sharing_image_with_the_asset(): void
    {
        $asset = $this->image();
        Page::where('slug', 'about')->update(['og_media_id' => $asset->id]);
        $asset->delete(); // file still on disk, as when deleting the file failed
        $this->assertArrayNotHasKey('og:image', $this->metaOf('/about'));
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        $this->post(route('admin.media.restore', $asset))->assertSessionHas('success');
        $this->app['auth']->forgetGuards();
        $this->assertSame(self::SITE.'/storage/'.$asset->path, $this->metaOf('/about')['og:image']);
    }
    public function test_robots_directives_and_noindex_behaviour_are_unchanged(): void
    {
        $this->assertSame('index,follow', $this->metaOf('/about')['robots']);
        Page::where('slug', 'about')->update(['robots_directive' => 'noindex,nofollow']);
        $this->assertSame('noindex,nofollow', $this->metaOf('/about')['robots']);
        $this->assertSame('noindex, follow', $this->get('/search?q=a')->headers->get('X-Robots-Tag'));
        // Demo mode on an allowlisted host always adds noindex, whatever the page says.
        config(['kelika.demo_content.public' => true, 'kelika.demo_content.hosts' => ['demo.kelika.test']]);
        Page::where('slug', 'about')->update(['robots_directive' => 'index,follow']);
        $response = $this->get('http://demo.kelika.test/about');
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $response->getContent());
    }
    public function test_search_and_404_pages_carry_site_metadata_but_no_canonical(): void
    {
        $search = $this->metaOf('/search?q=about');
        $this->assertArrayNotHasKey('canonical', $search);
        $this->assertArrayNotHasKey('og:url', $search);
        $this->assertSame(['KELIKA', 'Play. Grow. Belong.'], [$search['og:title'], $search['og:description']]);
        $this->get('/no-such-page')->assertNotFound()->assertDontSee('rel="canonical"', false);
    }
    public function test_the_sitemap_lists_only_indexable_live_pages_on_the_sites_own_host(): void
    {
        $this->page('external', 'External canonical', ['canonical_url' => 'https://partner.example/elsewhere']);
        $this->page('same-host', 'Same host canonical', ['canonical_url' => self::SITE.'/about']);
        $this->page('hidden', 'Hidden', ['robots_directive' => 'noindex,follow']);
        $this->page('draft', 'Draft', ['status' => 'draft']);
        $this->page('later', 'Later', ['status' => 'scheduled', 'published_at' => now()->addDay()]);
        $this->page('gone', 'Archived')->delete();
        $response = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
        preg_match_all('#<loc>([^<]+)</loc>#', $response->getContent(), $m);
        $locs = $m[1];
        sort($locs);
        $this->assertSame([self::SITE.'/', self::SITE.'/about', self::SITE.'/about', self::SITE.'/external'], $locs);
        foreach ($locs as $loc) $this->assertStringStartsWith(self::SITE.'/', $loc);
        $this->assertNotFalse(simplexml_load_string($response->getContent()), 'well-formed XML');
    }
    public function test_robots_txt_blocks_the_cms_and_points_at_the_sitemap(): void
    {
        $response = $this->get('/robots.txt')->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("User-agent: *\nDisallow: /admin/\n", $response->getContent());
        $this->assertMatchesRegularExpression('#Sitemap: https?://[^/\s]+/sitemap\.xml#', $response->getContent());
    }
}

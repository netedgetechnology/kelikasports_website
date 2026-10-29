<?php
namespace Tests\Feature;
use App\Http\Controllers\Admin\MediaController;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\PublicSiteData;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
/** The public website logos (header, phone header, footer): managed in Global Settings, separate from the CMS logo. */
class PublicLogoTest extends TestCase
{
    use RefreshDatabase;
    private const WORDMARK = '/images/kelika-wordmark.png';
    private string $root;
    private User $admin;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->root = sys_get_temp_dir().'/kelika-public-logo-test-'.bin2hex(random_bytes(6));
        foreach (['public', 'private'] as $dir) mkdir("{$this->root}/{$dir}", 0755, true);
        config(['filesystems.disks.public.root' => "{$this->root}/public", 'filesystems.disks.local.root' => "{$this->root}/private"]);
        Storage::forgetDisk(['public', 'local']);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]);
        Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'published_at' => now()->subDay()]);
        // As on the development site: all three public logo settings hold the built-in wordmark.
        Setting::write('site_name', 'KELIKA');
        foreach (['logo_url', 'mobile_logo_url', 'footer_logo_url'] as $key) Setting::write($key, self::WORDMARK, 'branding');
    }
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
        parent::tearDown();
    }
    private function save(array $fields)
    {
        $colors = array_fill_keys(['primary_color', 'terracotta_color', 'gold_color', 'earth_color', 'water_color', 'fire_color', 'air_color', 'space_color'], '#123abc');
        $current = ['logo_url' => Setting::read('logo_url'), 'mobile_logo_url' => Setting::read('mobile_logo_url'), 'footer_logo_url' => Setting::read('footer_logo_url')];
        return $this->actingAs($this->admin)->from(route('admin.settings.edit'))->put(route('admin.settings.update'), $fields + $current + $colors + ['site_name' => 'KELIKA', 'booking_button_label' => 'Book a Court', 'font_family' => 'Inter', 'button_style' => 'square']);
    }
    private function png(string $name = 'website-logo.png'): UploadedFile
    {
        $im = imagecreatetruecolor(460, 90);
        imagefill($im, 0, 0, imagecolorallocate($im, 6, 79, 67));
        imagepng($im, $path = $this->root.'/'.bin2hex(random_bytes(3)).'.png');
        return new UploadedFile($path, $name, 'image/png', null, true);
    }
    private function libraryImage(bool $withFile = true): MediaAsset
    {
        $path = 'media/2026/10/'.bin2hex(random_bytes(8)).'.png';
        if ($withFile) Storage::disk('public')->put($path, 'x');
        return MediaAsset::create(['disk' => 'public', 'path' => $path, 'original_name' => 'library-logo.png', 'mime_type' => 'image/png', 'size_bytes' => 1]);
    }
    /** The header brand link and footer brand link of a public page, as a visitor sees them. */
    private function brand(): array
    {
        $this->app['auth']->forgetGuards();
        $html = $this->get('/about')->assertOk()->getContent();
        $cut = function (string $marker) use ($html) { $s = strpos($html, $marker); return substr($html, $s, strpos($html, '</a>', $s) - $s); };
        return ['header' => $cut('<a class="navbar-brand"'), 'footer' => $cut('<a class="kl-footer__brand"')];
    }

    public function test_choosing_a_library_image_changes_the_header_phone_and_footer_logo(): void
    {
        $before = $this->brand();
        $this->assertStringContainsString(self::WORDMARK, $before['header']);
        $image = $this->libraryImage();
        $this->save(['logo_url' => $image->path])->assertSessionHasNoErrors();
        $this->assertSame([$image->path, null, null], [Setting::read('logo_url'), Setting::read('mobile_logo_url'), Setting::read('footer_logo_url')], 'copies of the old logo now follow the new one');
        $brand = $this->brand();
        $this->assertStringContainsString('src="'.e($image->url).'"', $brand['header']);
        $this->assertStringContainsString('src="'.e($image->url).'"', $brand['footer']);
        $this->assertStringNotContainsString('kelika-wordmark', $brand['header'].$brand['footer'], 'no old logo remains for phones or the footer');
    }
    public function test_an_uploaded_website_logo_is_stored_in_the_library_and_rendered_with_webp_copies(): void
    {
        $this->save(['logo_upload' => $this->png()])->assertSessionHasNoErrors()->assertSessionHas('success');
        $asset = MediaAsset::sole();
        $this->assertSame([$asset->path, 'Website logo', 'image/png'], [Setting::read('logo_url'), $asset->alt_text, $asset->mime_type]);
        $this->assertTrue(Storage::disk('public')->exists($asset->path));
        $brand = $this->brand();
        $this->assertStringContainsString('src="'.e($asset->url).'"', $brand['header']);
        $this->assertStringContainsString('/storage/'.$asset->variantsDirectory().'/w', $brand['header'], 'the WebP copies are offered');
        $this->assertStringContainsString('alt="KELIKA"', $brand['header']);
        $this->assertStringContainsString(e($asset->url), $brand['footer']);
        $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertSee('<option value="'.$asset->path.'" selected', false);
    }
    public function test_a_page_cached_before_the_change_shows_the_new_logo_straight_away(): void
    {
        $this->brand(); // builds and caches the shared public data
        $this->assertTrue(Cache::has(PublicSiteData::CACHE_KEY));
        $image = $this->libraryImage();
        $this->save(['logo_url' => $image->path]);
        $this->assertFalse(Cache::has(PublicSiteData::CACHE_KEY), 'saving settings clears the cached copy');
        $this->assertStringContainsString(e($image->url), $this->brand()['header']);
    }
    public function test_the_website_and_cms_logos_are_independent(): void
    {
        $cms = $this->libraryImage();
        $this->save(['admin_logo_media_id' => $cms->id])->assertSessionHasNoErrors();
        $this->assertSame(self::WORDMARK, Setting::read('logo_url'));
        $this->assertStringNotContainsString($cms->path, implode('', $this->brand()), 'the CMS logo is not shown to visitors');
        $website = $this->libraryImage();
        $this->save(['logo_url' => $website->path, 'admin_logo_media_id' => $cms->id]);
        $this->assertSame($cms->path, Setting::read('admin_logo_url'));
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->getContent();
        $this->assertStringContainsString(e($cms->url), $html);
        $this->assertStringNotContainsString($website->path, substr($html, strpos($html, '<aside'), strpos($html, '</aside>') - strpos($html, '<aside')));
    }
    public function test_a_deliberately_different_footer_or_phone_logo_is_kept(): void
    {
        $footer = $this->libraryImage();
        $this->save(['footer_logo_url' => $footer->path]);
        $website = $this->libraryImage();
        $this->save(['logo_url' => $website->path]);
        $this->assertSame([$website->path, $footer->path, null], [Setting::read('logo_url'), Setting::read('footer_logo_url'), Setting::read('mobile_logo_url')]);
        $brand = $this->brand();
        $this->assertStringContainsString(e($footer->url), $brand['footer']);
        $this->assertStringNotContainsString($footer->path, $brand['header']);
    }
    public function test_without_a_logo_the_site_name_is_shown(): void
    {
        $this->save(['logo_url' => '', 'mobile_logo_url' => '', 'footer_logo_url' => ''])->assertSessionHasNoErrors();
        $brand = $this->brand();
        $this->assertStringNotContainsString('<img', $brand['header'].$brand['footer']);
        $this->assertMatchesRegularExpression('/>\s*KELIKA\s*$/', $brand['header']);
        $this->assertStringEndsWith('>KELIKA', $brand['footer']);
    }
    public function test_a_missing_or_deleted_logo_file_falls_back_instead_of_showing_a_broken_image(): void
    {
        $image = $this->libraryImage();
        $this->save(['logo_url' => $image->path]);
        Storage::disk('public')->delete($image->path);
        $brand = $this->brand();
        $this->assertStringNotContainsString('<img', $brand['header'].$brand['footer']);
        $this->assertStringContainsString('KELIKA', $brand['header']);
        $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertSee('data-logo-unavailable="logo_url"', false);

        // A missing footer override falls back to the (available) website logo.
        $this->save(['logo_url' => self::WORDMARK, 'footer_logo_url' => $image->path]);
        $this->assertStringContainsString(self::WORDMARK, $this->brand()['footer']);
        $deleted = $this->libraryImage();
        $this->save(['logo_url' => $deleted->path]);
        $deleted->delete();
        $this->assertStringNotContainsString('<img', $this->brand()['header']);
    }
    public function test_unsafe_logo_uploads_and_values_are_rejected(): void
    {
        file_put_contents($fake = $this->root.'/fake.png', 'not an image');
        file_put_contents($svg = $this->root.'/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        foreach ([UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'), new UploadedFile($svg, 'logo.svg', 'image/svg+xml', null, true), new UploadedFile($fake, 'logo.png', 'image/png', null, true), UploadedFile::fake()->image('logo.png', 20, 20)->size(MediaController::maxUploadKb() + 1)] as $file) {
            $this->save(['logo_upload' => $file])->assertSessionHasErrors('logo_upload');
        }
        foreach (['javascript:alert(1)', '//evil.example/logo.png', 'media/../../.env'] as $bad) $this->save(['logo_url' => $bad])->assertSessionHasErrors('logo_url');
        $this->assertSame(0, MediaAsset::count());
        $this->assertSame(self::WORDMARK, Setting::read('logo_url'));
    }
    public function test_the_settings_form_separates_website_and_cms_logos_and_keeps_custom_values(): void
    {
        $image = $this->libraryImage();
        Setting::write('footer_logo_url', 'https://cdn.example.test/footer.png', 'branding');
        $html = $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertOk()->getContent();
        $this->assertStringContainsString('id="website-logo">Website logo</h3>', $html);
        $this->assertStringContainsString('CMS logo (admin panel only)', $html);
        $this->assertStringContainsString('<option value="'.self::WORDMARK.'" selected>KELIKA wordmark (built-in)</option>', preg_replace('/\s+/', ' ', $html));
        $this->assertStringContainsString('<option value="'.$image->path.'" >library-logo.png (Media Library)</option>', preg_replace('/\s+/', ' ', $html));
        $this->assertStringContainsString('<option value="https://cdn.example.test/footer.png" selected>Current: https://cdn.example.test/footer.png</option>', preg_replace('/\s+/', ' ', $html));
        $this->assertStringContainsString('name="logo_upload"', $html);
    }
}

<?php
namespace Tests\Feature;
use App\Http\Controllers\Admin\MediaController;
use App\Models\MediaAsset;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminBranding;
use App\Support\MediaUsage;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
/** CMS branding (sidebar logo and colour, kept in global settings) and the admin layout around it. */
class AdminBrandingTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->root = sys_get_temp_dir().'/kelika-admin-brand-test-'.bin2hex(random_bytes(6));
        foreach (['public', 'private'] as $dir) mkdir("{$this->root}/{$dir}", 0755, true);
        config(['filesystems.disks.public.root' => "{$this->root}/public", 'filesystems.disks.local.root' => "{$this->root}/private"]);
        Storage::forgetDisk(['public', 'local']);
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
    }
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
        parent::tearDown();
    }
    /** The settings form's required fields, so a request only changes what a test is about. */
    private function save(array $fields)
    {
        $colors = array_fill_keys(['primary_color', 'terracotta_color', 'gold_color', 'earth_color', 'water_color', 'fire_color', 'air_color', 'space_color'], '#123abc');
        return $this->from(route('admin.settings.edit'))->put(route('admin.settings.update'), $fields + $colors + ['site_name' => 'KELIKA', 'booking_button_label' => 'Book a Court', 'font_family' => 'Inter', 'button_style' => 'square']);
    }
    private function png(int $width = 240, int $height = 60): UploadedFile
    {
        $im = imagecreatetruecolor($width, $height);
        imagefill($im, 0, 0, imagecolorallocate($im, 20, 120, 90));
        imagepng($im, $path = $this->root.'/logo-'.bin2hex(random_bytes(3)).'.png');
        return new UploadedFile($path, 'cms-logo.png', 'image/png', null, true);
    }
    private function libraryImage(string $mime = 'image/png', bool $withFile = true): MediaAsset
    {
        $path = 'media/2026/10/'.bin2hex(random_bytes(8)).($mime === 'application/pdf' ? '.pdf' : '.png');
        if ($withFile) Storage::disk('public')->put($path, 'x');
        return MediaAsset::create(['disk' => 'public', 'path' => $path, 'original_name' => basename($path), 'mime_type' => $mime, 'size_bytes' => 1]);
    }
    private function sidebar(string $route = 'admin.dashboard', array $params = []): string
    {
        $html = $this->get(route($route, $params))->assertOk()->getContent();
        $start = strpos($html, '<aside');
        return substr($html, $start, strpos($html, '</aside>') - $start);
    }

    // Defaults and layout

    public function test_without_settings_the_sidebar_keeps_the_original_name_and_colour(): void
    {
        $sidebar = $this->sidebar();
        $this->assertStringContainsString('<span class="admin-brand__name">KELIKA</span>', $sidebar);
        $this->assertStringNotContainsString('<img', $sidebar);
        $this->assertStringContainsString('--admin-sidebar-bg:#073f36;--admin-sidebar-fg:#ffffff', $sidebar);
        $this->assertStringContainsString('Content Studio', $sidebar);
    }
    public function test_the_current_section_is_marked_in_the_navigation(): void
    {
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('admin.media.index'), '#').'" class="active"\s+aria-current="page"\s*>Media Library</a>#', $this->sidebar('admin.media.trash'));
        $sidebar = $this->sidebar('admin.content.index', ['type' => 'program']);
        $this->assertMatchesRegularExpression('#class="active"\s+aria-current="page"\s*>Programs</a>#', $sidebar);
        $this->assertSame(1, substr_count($sidebar, 'aria-current="page"'), 'only one item is current');
        $this->assertStringContainsString('class="btn btn-sm admin-sidebar-button w-100">Sign out', $sidebar);
    }
    public function test_media_cards_fit_more_per_row_with_compact_actions(): void
    {
        $asset = $this->libraryImage();
        $html = $this->get(route('admin.media.index'))->getContent();
        $this->assertStringContainsString('<div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 row-cols-xxl-5 g-2">', $html);
        $this->assertStringContainsString('<div class="col" data-media-card="'.$asset->id.'"><div class="admin-panel h-100 media-card">', $html);
        $this->assertStringContainsString('class="media-card__thumb"', $html);
        $this->assertStringContainsString('<details><summary>Edit details</summary>', $html);
        $this->assertStringContainsString('<div class="media-card__actions">', $html);
    }

    // Sidebar colour

    public function test_a_valid_colour_is_saved_and_applied_with_readable_text(): void
    {
        $this->save(['admin_sidebar_color' => '#F5D90A'])->assertSessionHasNoErrors();
        $this->assertSame('#F5D90A', Setting::read('admin_sidebar_color'));
        $this->assertSame('cms', Setting::where('key', 'admin_sidebar_color')->value('group'));
        $sidebar = $this->sidebar();
        $this->assertStringContainsString('--admin-sidebar-bg:#f5d90a;--admin-sidebar-fg:#000000', $sidebar, 'a light colour gets black text');
        $this->save(['admin_sidebar_color' => '#1b2a6b']);
        $this->assertStringContainsString('--admin-sidebar-bg:#1b2a6b;--admin-sidebar-fg:#ffffff', $this->sidebar(), 'a dark colour gets white text');
        $this->get(route('admin.settings.edit'))->assertSee('value="#1b2a6b" pattern="#[0-9a-fA-F]{6}"', false)->assertSee('type="color" value="#1b2a6b"', false);
    }
    public function test_sidebar_text_always_has_enough_contrast(): void
    {
        foreach (['#000000', '#ffffff', '#777777', '#808080', '#ff0000', '#00ff00', '#0000ff', '#ffff00', '#1687c9', '#073f36'] as $color) {
            $sidebar = AdminBranding::sidebar($color);
            $this->assertGreaterThanOrEqual(4.5, AdminBranding::contrast($sidebar['--admin-sidebar-bg'], $sidebar['--admin-sidebar-fg']), $color);
        }
    }
    public function test_invalid_colours_are_rejected_and_nothing_is_saved(): void
    {
        $this->save(['admin_sidebar_color' => '#1b2a6b']);
        foreach (['red', '#12345', '#1234567', '#gggggg', '1b2a6b', '#fff;background:url(x)', '"><script>alert(1)</script>'] as $bad) {
            $this->save(['admin_sidebar_color' => $bad])->assertSessionHasErrors(['admin_sidebar_color' => 'The sidebar colour must be a hex colour such as #073f36.']);
        }
        $this->assertSame('#1b2a6b', Setting::read('admin_sidebar_color'));
    }
    public function test_a_tampered_stored_colour_falls_back_to_the_default_and_is_never_output(): void
    {
        Setting::write('admin_sidebar_color', '#000;}</style><script>alert(1)</script>', 'cms');
        $sidebar = $this->sidebar();
        $this->assertStringContainsString('--admin-sidebar-bg:#073f36', $sidebar);
        $this->assertStringNotContainsString('alert(1)', $sidebar);
    }
    public function test_choosing_the_default_or_emptying_the_colour_restores_the_original(): void
    {
        $this->save(['admin_sidebar_color' => '#1b2a6b']);
        $this->save(['admin_sidebar_color' => '#073F36'])->assertSessionHasNoErrors();
        $this->assertNull(Setting::read('admin_sidebar_color'));
        $this->save(['admin_sidebar_color' => '#1b2a6b']);
        $this->save(['admin_sidebar_color' => ''])->assertSessionHasNoErrors();
        $this->assertStringContainsString('--admin-sidebar-bg:#073f36', $this->sidebar());
    }

    // Logo

    public function test_an_uploaded_logo_goes_to_the_media_library_and_replaces_the_name(): void
    {
        $this->save(['admin_logo_media_id' => '', 'admin_logo_upload' => $this->png()])->assertSessionHasNoErrors()->assertSessionHas('success');
        $asset = MediaAsset::sole();
        $this->assertSame(['image/png', 'CMS logo'], [$asset->mime_type, $asset->alt_text]);
        $this->assertStringStartsWith('media/', $asset->path);
        $this->assertTrue(Storage::disk('public')->exists($asset->path));
        $this->assertSame($asset->path, Setting::read('admin_logo_url'));
        $sidebar = $this->sidebar();
        $this->assertStringContainsString('<img class="admin-brand__logo" src="'.e($asset->url).'" alt="KELIKA CMS">', $sidebar);
        $this->assertStringNotContainsString('admin-brand__name', $sidebar);
        // The settings form shows it as the chosen logo, and it is protected from deletion while in use.
        $this->get(route('admin.settings.edit'))->assertSee('<option value="'.$asset->id.'" selected', false);
        $this->assertSame(MediaUsage::IN_USE, MediaUsage::report([$asset])[$asset->id]['state']);
        $this->delete(route('admin.media.destroy', $asset))->assertSessionHasErrors('media');
    }
    public function test_a_second_upload_replaces_the_logo_and_keeps_the_first_in_the_library(): void
    {
        $this->save(['admin_logo_upload' => $this->png()]);
        $first = MediaAsset::sole();
        $this->save(['admin_logo_media_id' => $first->id, 'admin_logo_upload' => $this->png(300, 80)])->assertSessionHasNoErrors();
        $second = MediaAsset::latest('id')->first();
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($second->path, Setting::read('admin_logo_url'), 'an upload wins over the list');
        $this->assertSame(2, MediaAsset::count());
    }
    public function test_unsafe_oversized_and_non_image_uploads_are_rejected_without_saving_anything(): void
    {
        $kb = MediaController::maxUploadKb();
        file_put_contents($fake = $this->root.'/fake.png', 'not really an image');
        $svg = $this->root.'/logo.svg';
        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $cases = [
            'pdf' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            'svg' => new UploadedFile($svg, 'logo.svg', 'image/svg+xml', null, true),
            'text pretending to be png' => new UploadedFile($fake, 'logo.png', 'image/png', null, true),
            'too large' => UploadedFile::fake()->image('logo.png', 20, 20)->size($kb + 1),
        ];
        foreach ($cases as $case => $file) {
            $this->save(['admin_logo_upload' => $file, 'admin_sidebar_color' => '#1b2a6b'])->assertSessionHasErrors('admin_logo_upload');
            $this->assertSame(0, MediaAsset::count(), $case);
        }
        $this->assertNull(Setting::read('admin_logo_url'));
        $this->assertNull(Setting::read('admin_sidebar_color'), 'nothing else from a rejected form was saved');
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->save(['admin_logo_upload' => UploadedFile::fake()->image('logo.png', 20, 20)->size($kb + 1)])->assertSessionHasErrors(['admin_logo_upload' => 'Files must be '.MediaController::formatKb($kb).' or smaller.']);
    }
    public function test_an_existing_library_image_can_be_chosen_and_cleared(): void
    {
        $image = $this->libraryImage();
        $this->save(['admin_logo_media_id' => $image->id])->assertSessionHasNoErrors();
        $this->assertSame($image->path, Setting::read('admin_logo_url'));
        $this->assertStringContainsString('src="'.e($image->url).'"', $this->sidebar());
        $this->save(['admin_logo_media_id' => ''])->assertSessionHasNoErrors();
        $this->assertSame('', (string) Setting::read('admin_logo_url'));
        $this->assertStringContainsString('admin-brand__name', $this->sidebar());
    }
    public function test_deleted_missing_pdf_and_unknown_media_cannot_be_the_logo(): void
    {
        $deleted = $this->libraryImage();
        $deleted->delete();
        foreach (['deleted' => $deleted->id, 'missing file' => $this->libraryImage('image/png', false)->id, 'pdf' => $this->libraryImage('application/pdf')->id, 'unknown' => 999999, 'not a number' => 'abc'] as $case => $id) {
            $this->save(['admin_logo_media_id' => $id])->assertSessionHasErrors('admin_logo_media_id');
            $this->assertNull(Setting::read('admin_logo_url'), $case);
        }
    }
    public function test_a_logo_whose_file_disappears_falls_back_to_the_name_and_is_flagged(): void
    {
        $image = $this->libraryImage();
        $this->save(['admin_logo_media_id' => $image->id]);
        Storage::disk('public')->delete($image->path);
        $this->assertStringContainsString('admin-brand__name', $this->sidebar());
        $this->get(route('admin.settings.edit'))->assertSee('data-admin-logo-unavailable', false);
        Setting::write('admin_logo_url', '/etc/passwd', 'cms');
        $this->assertStringNotContainsString('<img', $this->sidebar(), 'only media library paths are used');
    }
    public function test_leaving_out_the_logo_fields_keeps_the_logo(): void
    {
        $image = $this->libraryImage();
        $this->save(['admin_logo_media_id' => $image->id]);
        $this->save(['site_tagline' => 'Changed'])->assertSessionHasNoErrors();
        $this->assertSame($image->path, Setting::read('admin_logo_url'));
    }
    public function test_editors_see_the_branding_and_the_public_site_is_unaffected(): void
    {
        $this->save(['admin_sidebar_color' => '#1b2a6b', 'admin_logo_upload' => $this->png()]);
        $this->actingAs(User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]));
        $this->assertStringContainsString('--admin-sidebar-bg:#1b2a6b', $this->sidebar());
        \App\Models\Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertOk()->assertDontSee('admin-sidebar', false)->assertDontSee('#1b2a6b', false);
    }
}

<?php
namespace Tests\Feature;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Setting;
use App\Models\User;
use App\Support\MediaUsage;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class MediaLibraryUsageTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    private Page $home;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Media lives in a throwaway folder outside the project.
        $this->root = sys_get_temp_dir().'/kelika-library-test-'.bin2hex(random_bytes(6));
        foreach (['public', 'private'] as $dir) mkdir("{$this->root}/{$dir}", 0755, true);
        config(['filesystems.disks.public.root' => "{$this->root}/public", 'filesystems.disks.local.root' => "{$this->root}/private"]);
        Storage::forgetDisk(['public', 'local']);
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        $this->home = Page::create(['title' => 'Welcome to KELIKA', 'slug' => 'home', 'status' => 'draft']);
    }
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
        parent::tearDown();
    }
    /** A record whose file may or may not exist; no upload pipeline. */
    private function record(string $name = 'aa11bb22-0000-4000-8000-000000000001.jpg', string $mime = 'image/jpeg', bool $withFile = false): MediaAsset
    {
        $path = 'media/2026/10/'.$name;
        if ($withFile) Storage::disk('public')->put($path, 'x');
        return MediaAsset::create(['disk' => 'public', 'path' => $path, 'original_name' => $name, 'mime_type' => $mime, 'size_bytes' => 1]);
    }
    /** A real upload, so WebP copies are generated. */
    private function uploaded(): MediaAsset
    {
        $im = imagecreatetruecolor(1300, 866);
        for ($y = 0; $y < 866; $y += 6) for ($x = 0; $x < 1300; $x += 6) imagefilledrectangle($im, $x, $y, $x + 5, $y + 5, imagecolorallocate($im, ($x + $y) % 256, $x % 256, $y % 256));
        imagejpeg($im, $file = $this->root.'/photo.jpg', 90);
        $this->post(route('admin.media.store'), ['file' => new UploadedFile($file, 'court.jpg', null, null, true)])->assertSessionHasNoErrors();
        return MediaAsset::latest('id')->firstOrFail();
    }
    private function card(string $html, MediaAsset $asset): string
    {
        $start = strpos($html, 'action="'.route('admin.media.update', $asset).'"');
        $open = strrpos(substr($html, 0, $start), '<div class="col" data-media-card="'.$asset->id.'">');
        return substr($html, $open, strpos($html, route('admin.media.destroy', $asset), $start) - $open);
    }

    // Usage
    public function test_an_asset_used_by_a_section_shows_the_page_and_section(): void
    {
        $asset = $this->record();
        PageSection::create(['page_id' => $this->home->id, 'type' => 'hero', 'admin_label' => 'Main banner', 'data' => ['image' => $asset->path]]);
        $card = $this->card($this->get(route('admin.media.index'))->assertOk()->getContent(), $asset);
        $this->assertStringContainsString('data-usage="in_use"', $card);
        $this->assertStringContainsString('In use', $card);
        $this->assertStringContainsString('hero section #', $card);
        $this->assertStringContainsString('on page &quot;home&quot; (section &quot;Main banner&quot;) · Welcome to KELIKA', $card);
    }
    public function test_an_asset_used_in_several_places_lists_each_of_them(): void
    {
        $asset = $this->record();
        PageSection::create(['page_id' => $this->home->id, 'type' => 'cta', 'data' => ['image' => $asset->path]]);
        ContentItem::create(['type' => 'coach', 'title' => 'Head Coach', 'status' => 'draft', 'data' => ['image' => 'https://dev.kelikasports.com/storage/'.$asset->path]]);
        Setting::write('logo_url', '/storage/'.$asset->path, 'branding');
        Menu::create(['name' => 'Footer', 'location' => 'footer', 'is_active' => true])->menuItems()->create(['label' => 'Brochure', 'url' => '/storage/'.$asset->path, 'target' => '_blank', 'sort_order' => 1]);
        $other = Page::create(['title' => 'About us', 'slug' => 'about', 'status' => 'draft', 'og_media_id' => $asset->id]);

        $report = MediaUsage::report([$asset])[$asset->id];
        $this->assertSame(MediaUsage::IN_USE, $report['state']);
        $labels = array_column($report['uses'], 'label');
        sort($labels);
        $this->assertSame(['coach "Head Coach"', 'cta section #'.PageSection::first()->id.' on page "home"', 'menu item "Brochure"', 'site setting logo_url', 'social sharing image of page "about"'], $labels);
        $this->assertContains('About us', array_column($report['uses'], 'context'));
        $this->assertSame($labels, (function () use ($asset) { $l = $asset->usages(); sort($l); return $l; })(), 'deletion sees the same uses');
    }
    public function test_references_by_path_url_and_in_text_are_told_apart(): void
    {
        $linked = $this->record('aa11bb22-0000-4000-8000-000000000002.pdf', 'application/pdf');
        PageSection::create(['page_id' => $this->home->id, 'type' => 'cta', 'data' => ['button_url' => '/storage/'.$linked->path]]);
        $mentioned = $this->record('aa11bb22-0000-4000-8000-000000000003.jpg');
        PageSection::create(['page_id' => $this->home->id, 'type' => 'rich_text', 'admin_label' => 'Notes', 'data' => ['body' => 'Old file was '.$mentioned->path.', do not use.']]);
        $unicode = $this->record('café-court.jpg');
        PageSection::create(['page_id' => $this->home->id, 'type' => 'split', 'data' => ['image' => $unicode->path]]); // stored with \u escapes

        $report = MediaUsage::report([$linked, $mentioned, $unicode]);
        $this->assertSame(MediaUsage::IN_USE, $report[$linked->id]['state'], 'a link field is a confirmed use');
        $this->assertSame(MediaUsage::MENTIONED, $report[$mentioned->id]['state'], 'a name inside text is uncertain');
        $this->assertStringEndsWith('(mentioned in text)', $report[$mentioned->id]['uses'][0]['label']);
        $this->assertSame(MediaUsage::IN_USE, $report[$unicode->id]['state']);

        $card = $this->card($this->get(route('admin.media.index'))->getContent(), $mentioned);
        $this->assertStringContainsString('Possibly in use', $card);
        // Mentions still protect the file from deletion, as before.
        $this->from(route('admin.media.index'))->delete(route('admin.media.destroy', $mentioned))->assertSessionHasErrors('media');
        $this->assertNotSoftDeleted($mentioned);
    }
    public function test_an_asset_with_no_references_is_labelled_without_being_deleted(): void
    {
        $asset = $this->record(withFile: true);
        $card = $this->card($this->get(route('admin.media.index'))->getContent(), $asset);
        $this->assertStringContainsString('data-usage="unused"', $card);
        $this->assertStringContainsString('No references found', $card);
        $this->assertStringContainsString('Checked: page sections, content items (including archived), site settings, menu links, redirects, page sharing images.', $card);
        $this->assertNotSoftDeleted($asset);
        $this->assertTrue(Storage::disk('public')->exists($asset->path));
    }
    public function test_an_asset_whose_name_cannot_be_checked_is_reported_as_unknown_and_stays_protected(): void
    {
        $asset = MediaAsset::create(['disk' => 'public', 'path' => 'media/2026/10/', 'original_name' => 'broken', 'mime_type' => 'image/jpeg', 'size_bytes' => 0]);
        $this->assertSame(MediaUsage::UNKNOWN, MediaUsage::report([$asset])[$asset->id]['state']);
        $this->assertStringContainsString('Usage unknown', $this->card($this->get(route('admin.media.index'))->getContent(), $asset));
        $this->from(route('admin.media.index'))->delete(route('admin.media.destroy', $asset))->assertSessionHasErrors('media');
    }
    public function test_a_missing_file_is_flagged_and_its_usage_still_reported(): void
    {
        $asset = $this->record();
        PageSection::create(['page_id' => $this->home->id, 'type' => 'hero', 'data' => ['image' => $asset->path]]);
        $card = $this->card($this->get(route('admin.media.index'))->getContent(), $asset);
        $this->assertStringContainsString('File missing', $card);
        $this->assertStringNotContainsString('<img', $card);
        $this->assertStringContainsString('data-usage="in_use"', $card);
    }

    // Thumbnails
    public function test_thumbnails_use_the_small_webp_copies_and_keep_a_link_to_the_original(): void
    {
        $asset = $this->uploaded();
        $card = $this->card($this->get(route('admin.media.index'))->getContent(), $asset);
        $this->assertMatchesRegularExpression('#<img src="[^"]*/storage/'.preg_quote($asset->variantsDirectory(), '#').'/w400-[0-9a-f]{12}\.webp"\s+srcset="[^"]+ 400w, [^"]+w800-[0-9a-f]{12}\.webp 800w"#', $card);
        $this->assertStringContainsString('data-preview="optimised"', $card);
        $this->assertStringContainsString('loading="lazy"', $card);
        $this->assertStringNotContainsString('<img src="'.$asset->url.'"', $card, 'the full-size original is not the thumbnail');
        $this->assertStringContainsString('<a href="'.$asset->url.'" target="_blank" rel="noopener">Open original</a>', $card);
    }
    public function test_thumbnails_fall_back_to_the_original_when_copies_are_missing(): void
    {
        $asset = $this->uploaded();
        $files = glob(Storage::disk('public')->path($asset->variantsDirectory()).'/w400-*.webp');
        unlink($files[0]);
        $card = $this->card($this->get(route('admin.media.index'))->getContent(), $asset);
        $this->assertMatchesRegularExpression('#<img src="[^"]*/w800-[0-9a-f]{12}\.webp"\s+srcset="[^"]+ 800w"#', $card, 'next smallest copy');
        (new Filesystem)->deleteDirectory(Storage::disk('public')->path($asset->variantsDirectory()));
        $card = $this->card($this->get(route('admin.media.index'))->getContent(), $asset);
        $this->assertStringContainsString('<img src="'.$asset->url.'"', $card);
        $this->assertStringContainsString('data-preview="original"', $card);
    }
    public function test_documents_show_a_placeholder_and_an_open_link(): void
    {
        $pdf = $this->record('aa11bb22-0000-4000-8000-000000000009.pdf', 'application/pdf', true);
        $card = $this->card($this->get(route('admin.media.index'))->getContent(), $pdf);
        $this->assertStringContainsString('Document', $card);
        $this->assertStringContainsString('Open file', $card);
        $this->assertStringNotContainsString('<img', $card);
    }

    public function test_the_library_uses_a_fixed_number_of_queries_however_many_assets_are_shown(): void
    {
        $count = function () {
            DB::flushQueryLog(); DB::enableQueryLog();
            $this->get(route('admin.media.index'))->assertOk();
            DB::disableQueryLog();
            return count(DB::getQueryLog());
        };
        foreach (range(1, 2) as $i) $this->record(sprintf('aa11bb22-0000-4000-8000-%012d.jpg', 100 + $i), withFile: true);
        $few = $count();
        foreach (range(1, 20) as $i) {
            $asset = $this->record(sprintf('aa11bb22-0000-4000-8000-%012d.jpg', 200 + $i), withFile: true);
            if ($i % 3 === 0) PageSection::create(['page_id' => $this->home->id, 'type' => 'hero', 'data' => ['image' => $asset->path]]);
        }
        $this->assertSame($few, $count());
    }
}

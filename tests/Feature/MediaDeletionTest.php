<?php
namespace Tests\Feature;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\User;
use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
class MediaDeletionTest extends TestCase
{
    use RefreshDatabase;
    private const NAME = 'ee463e1b-2142-4dbb-9a16-bf288e011f36.jpg';
    private string $diskRoot;
    protected function setUp(): void
    {
        parent::setUp();
        // The public disk points at a throwaway directory outside the project, never at storage/app/public.
        $this->diskRoot = sys_get_temp_dir().'/kelika-media-test-'.bin2hex(random_bytes(6));
        config(['filesystems.disks.public.root' => $this->diskRoot]);
        Storage::forgetDisk('public');
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
    }
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->diskRoot);
        parent::tearDown();
    }
    private function asset(string $name = self::NAME): MediaAsset
    {
        $path = 'media/2026/10/'.$name;
        Storage::disk('public')->put($path, 'image-bytes');
        return MediaAsset::create(['disk' => 'public', 'path' => $path, 'original_name' => 'court.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 11]);
    }
    private function deleteAsset(MediaAsset $asset)
    {
        return $this->from(route('admin.media.index'))->delete(route('admin.media.destroy', $asset));
    }
    private function assertKept(MediaAsset $asset): void
    {
        $this->assertNotSoftDeleted($asset);
        Storage::disk('public')->assertExists($asset->path);
    }
    private static function page(string $slug, array $attributes = []): Page
    {
        return Page::create(['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'published'] + $attributes);
    }
    public function test_unused_asset_is_archived_and_its_file_removed(): void
    {
        $asset = $this->asset();
        $this->deleteAsset($asset)->assertRedirect(route('admin.media.index'))->assertSessionHas('success', 'Media asset deleted.');
        $this->assertSoftDeleted($asset);
        Storage::disk('public')->assertMissing($asset->path);
    }
    public function test_references_to_other_files_do_not_block_deletion(): void
    {
        // "_" is a LIKE wildcard: a reference to "aXb.jpg" must not count as a reference to "a_b.jpg".
        $asset = $this->asset('a_b.jpg');
        PageSection::create(['page_id' => self::page('home')->id, 'type' => 'hero', 'data' => ['image' => 'media/2026/10/aXb.jpg', 'body' => 'media/2026/10/other.jpg']]);
        $this->deleteAsset($asset)->assertSessionHasNoErrors();
        $this->assertSoftDeleted($asset);
    }
    /** Each scenario stores a reference the way that part of the CMS does, and names the label the error must show. */
    public static function references(): array
    {
        return [
            'section image (JSON with escaped slashes)' => [fn (MediaAsset $a) => PageSection::create(['page_id' => self::page('home')->id, 'type' => 'hero', 'data' => ['image' => $a->path]]), 'hero section #'],
            'section item as absolute URL (unescaped slashes)' => [fn (MediaAsset $a) => DB::table('page_sections')->insert(['page_id' => self::page('home')->id, 'type' => 'card_grid', 'sort_order' => 1, 'is_visible' => true, 'data' => json_encode(['items' => [['image' => 'https://dev.kelikasports.com/storage/'.$a->path]]], JSON_UNESCAPED_SLASHES), 'created_at' => now(), 'updated_at' => now()]), 'card_grid section #'],
            'link inside section body text' => [fn (MediaAsset $a) => PageSection::create(['page_id' => self::page('about')->id, 'type' => 'rich_text', 'data' => ['body' => 'Download /storage/'.$a->path.' for details.']]), 'on page "about"'],
            'content item' => [fn (MediaAsset $a) => ContentItem::create(['type' => 'event', 'title' => 'Winter Cup', 'status' => 'published', 'data' => ['image' => $a->path]]), 'event "Winter Cup"'],
            'archived content item' => [fn (MediaAsset $a) => ContentItem::create(['type' => 'coach', 'title' => 'Head Coach', 'status' => 'draft', 'data' => ['image' => $a->path]])->delete(), 'coach "Head Coach" (archived)'],
            'site setting' => [fn (MediaAsset $a) => Setting::write('logo_url', $a->url, 'branding'), 'site setting logo_url'],
            'menu item link' => [fn (MediaAsset $a) => Menu::create(['name' => 'Footer', 'location' => 'footer', 'is_active' => true])->menuItems()->create(['label' => 'Price list', 'url' => '/storage/'.$a->path, 'target' => '_self', 'sort_order' => 1]), 'menu item "Price list"'],
            'redirect destination' => [fn (MediaAsset $a) => Redirect::create(['from_path' => '/brochure', 'to_path' => '/storage/'.$a->path, 'status_code' => 302, 'is_active' => true]), 'redirect from /brochure'],
            'page social sharing image' => [fn (MediaAsset $a) => self::page('events', ['og_media_id' => $a->id]), 'social sharing image of page "events"'],
            'archived page social sharing image' => [fn (MediaAsset $a) => self::page('old-news', ['og_media_id' => $a->id])->delete(), 'page "old-news" (archived)'],
        ];
    }
    #[DataProvider('references')]
    public function test_referenced_asset_is_not_deleted(Closure $reference, string $label): void
    {
        $asset = $this->asset();
        $reference($asset);
        $this->deleteAsset($asset)->assertRedirect(route('admin.media.index'))->assertSessionHasErrors('media');
        $this->assertStringContainsString($label, session('errors')->first('media'));
        $this->assertKept($asset);
    }
    public function test_reference_with_json_unicode_escapes_is_detected(): void
    {
        // The array cast stores "é" as é, so the plain file name never appears in the column.
        $asset = $this->asset('café-court.jpg');
        PageSection::create(['page_id' => self::page('home')->id, 'type' => 'hero', 'data' => ['image' => $asset->path]]);
        $this->assertStringNotContainsString('café', DB::table('page_sections')->value('data'));
        $this->deleteAsset($asset)->assertSessionHasErrors('media');
        $this->assertKept($asset);
    }
    public function test_inconclusive_reference_check_blocks_deletion(): void
    {
        Exceptions::fake();
        $asset = MediaAsset::create(['disk' => 'public', 'path' => 'media/2026/10/', 'original_name' => 'broken', 'mime_type' => 'image/jpeg', 'size_bytes' => 0]);
        $this->deleteAsset($asset)->assertSessionHasErrors(['media' => 'This asset could not be safely confirmed as unused and deleted, so nothing was changed. Please try again.']);
        $this->assertNotSoftDeleted($asset);
        Exceptions::assertReported(fn (RuntimeException $e) => str_contains($e->getMessage(), 'no usable file name'));
    }
    public function test_failed_database_change_keeps_the_record_and_the_file(): void
    {
        Exceptions::fake();
        $asset = $this->asset();
        MediaAsset::deleting(fn () => throw new RuntimeException('Simulated database failure'));
        $this->deleteAsset($asset)->assertSessionHasErrors('media');
        $this->assertKept($asset);
        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Simulated database failure');
    }
}

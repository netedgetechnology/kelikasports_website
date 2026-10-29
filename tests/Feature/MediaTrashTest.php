<?php
namespace Tests\Feature;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use App\Support\MediaUsage;
use App\Support\PublicSiteData;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
/** The media trash: listing deleted assets honestly and restoring their records without inventing files. */
class MediaTrashTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    private User $admin;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Media lives in a throwaway folder outside the project.
        $this->root = sys_get_temp_dir().'/kelika-trash-test-'.bin2hex(random_bytes(6));
        foreach (['public', 'private'] as $dir) mkdir("{$this->root}/{$dir}", 0755, true);
        config(['filesystems.disks.public.root' => "{$this->root}/public", 'filesystems.disks.local.root' => "{$this->root}/private"]);
        Storage::forgetDisk(['public', 'local']);
        $this->admin = User::create(['name' => 'Admin Person', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]);
        $this->actingAs($this->admin);
    }
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
        parent::tearDown();
    }
    private function record(string $name, bool $withFile = false, string $mime = 'image/jpeg'): MediaAsset
    {
        $path = 'media/2026/10/'.$name;
        if ($withFile) Storage::disk('public')->put($path, str_repeat('x', 2048));
        return MediaAsset::create(['disk' => 'public', 'path' => $path, 'original_name' => $name, 'mime_type' => $mime, 'size_bytes' => 250880, 'uploaded_by' => $this->admin->id]);
    }
    private function jpeg(): string
    {
        $im = imagecreatetruecolor(1300, 866);
        for ($y = 0; $y < 866; $y += 6) for ($x = 0; $x < 1300; $x += 6) imagefilledrectangle($im, $x, $y, $x + 5, $y + 5, imagecolorallocate($im, ($x + $y) % 256, $x % 256, $y % 256));
        imagejpeg($im, $file = $this->root.'/photo-'.bin2hex(random_bytes(3)).'.jpg', 90);
        return $file;
    }
    /** A real upload, so the original and its WebP copies exist. */
    private function uploaded(string $name = 'court.jpg'): MediaAsset
    {
        $this->post(route('admin.media.store'), ['file' => new UploadedFile($this->jpeg(), $name, null, null, true)])->assertSessionHasNoErrors();
        return MediaAsset::latest('id')->firstOrFail();
    }
    private function pickerIds(): array
    {
        return collect($this->getJson(route('admin.media.picker'))->assertOk()->json('data'))->pluck('id')->all();
    }
    private function row(string $html, MediaAsset $asset): string
    {
        $start = strpos($html, 'data-trashed-media="'.$asset->id.'"');
        $this->assertNotFalse($start, "asset {$asset->id} is listed");
        return substr($html, $start, strpos($html, '</tr>', $start) - $start);
    }
    private function allFiles(): array
    {
        return array_merge(Storage::disk('public')->allFiles(), Storage::disk('local')->allFiles());
    }

    // Listing

    public function test_the_trash_lists_deleted_assets_with_their_details_and_file_state(): void
    {
        $missing = $this->record('aa000001-0000-4000-8000-000000000001.jpg');
        $kept = $this->record('aa000002-0000-4000-8000-000000000002.png', true, 'image/png');
        $active = $this->record('aa000003-0000-4000-8000-000000000003.jpg', true);
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 15));
        $missing->delete();
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(18, 5));
        $kept->delete();
        $this->travelBack();

        $html = $this->get(route('admin.media.trash'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-trashed-media="'.$active->id.'"', $html, 'active assets are not in the trash');
        $this->assertLessThan(strpos($html, 'data-trashed-media="'.$missing->id.'"'), strpos($html, 'data-trashed-media="'.$kept->id.'"'), 'most recently deleted first');
        $row = $this->row($html, $missing);
        foreach (['aa000001-0000-4000-8000-000000000001.jpg', 'image/jpeg', '245 KB', '20 Oct 2026 09:15', 'Admin Person', 'data-original="missing"', 'Original missing'] as $expected) $this->assertStringContainsString($expected, $row);
        $this->assertStringContainsString('Its file is missing and cannot be recovered', $row, 'the confirmation says the file will not come back');
        $this->assertStringContainsString('<form method="post" action="'.route('admin.media.restore', $missing).'">', $row);
        $row = $this->row($html, $kept);
        $this->assertStringContainsString('data-original="available"', $row);
        $this->assertStringContainsString('data-confirm="Restore “aa000002-0000-4000-8000-000000000002.png” to the media library?"', $row);
        $this->get(route('admin.media.index'))->assertSee('Media trash (2)');
    }
    public function test_the_trash_is_paginated_and_queries_stay_bounded(): void
    {
        foreach (range(1, 30) as $i) $this->record(sprintf('bb%06d-0000-4000-8000-000000000000.jpg', $i))->delete();
        DB::enableQueryLog();
        $first = $this->get(route('admin.media.trash'))->assertOk()->getContent();
        $queries = count(DB::getQueryLog());
        $this->assertSame(24, substr_count($first, 'data-trashed-media='));
        $this->assertStringContainsString('page=2', $first);
        $this->assertSame(6, substr_count($this->get(route('admin.media.trash', ['page' => 2]))->getContent(), 'data-trashed-media='));
        $this->assertLessThan(15, $queries, 'uploaders are loaded together, not once per row');
        $this->get(route('admin.media.trash', ['page' => 3]))->assertOk()->assertSee('The media trash is empty.');
    }
    public function test_deleted_assets_stay_out_of_the_library_picker_and_usage_until_restored(): void
    {
        $asset = $this->record('cc000001-0000-4000-8000-000000000001.jpg', true);
        $asset->delete(); // its file is still on disk, as when deleting the file failed
        $this->assertStringNotContainsString($asset->path, $this->get(route('admin.media.index'))->getContent());
        $this->assertNotContains($asset->id, $this->pickerIds());
        $this->assertNull(MediaAsset::find($asset->id));

        $this->post(route('admin.media.restore', $asset))->assertRedirect(route('admin.media.index', ['q' => $asset->original_name]));
        $this->assertStringContainsString($asset->path, $this->get(route('admin.media.index'))->getContent());
        $this->assertContains($asset->id, $this->pickerIds());
        $this->assertSame(MediaUsage::UNUSED, MediaUsage::report([$asset->fresh()])[$asset->id]['state']);
    }

    // Restoring

    public function test_restoring_brings_back_the_same_record_and_files_without_duplicates(): void
    {
        $asset = $this->uploaded();
        $variants = Storage::disk('public')->allFiles($asset->variantsDirectory());
        $this->assertNotEmpty($variants);
        $asset->delete(); // files left in place
        $before = MediaAsset::withTrashed()->find($asset->id)->getAttributes();
        $files = $this->allFiles();

        $this->post(route('admin.media.restore', $asset))->assertSessionHas('success', '“court.jpg” was restored to the library with its files.');
        $after = MediaAsset::find($asset->id)->getAttributes();
        $this->assertNull($after['deleted_at']);
        $this->assertSame(array_diff_key($before, ['deleted_at' => 1, 'updated_at' => 1]), array_diff_key($after, ['deleted_at' => 1, 'updated_at' => 1]));
        $this->assertSame(1, MediaAsset::withTrashed()->count(), 'no new record');
        $this->assertSame($files, $this->allFiles(), 'no file was created, copied or removed');
        $this->get(route('admin.media.trash'))->assertSee('The media trash is empty.');
    }
    public function test_restoring_an_asset_whose_files_were_deleted_marks_it_missing_and_replace_brings_it_back(): void
    {
        $asset = $this->uploaded();
        $this->delete(route('admin.media.destroy', $asset))->assertSessionHas('success', 'Media asset deleted.');
        $this->assertFalse(Storage::disk('public')->exists($asset->path));
        $this->assertStringContainsString('data-original="missing"', $this->row($this->get(route('admin.media.trash'))->getContent(), $asset));

        $this->post(route('admin.media.restore', $asset))->assertSessionHas('success', fn ($m) => str_contains($m, 'its file is no longer in storage and could not be recovered') && str_contains($m, 'Replace file'));
        $this->assertFalse(Storage::disk('public')->exists($asset->path), 'no file was invented');
        $this->assertSame([], Storage::disk('public')->allFiles($asset->variantsDirectory()));
        $card = $this->get(route('admin.media.index'))->getContent();
        $this->assertStringContainsString('File missing', $card);
        $this->assertStringContainsString('Upload it again with Replace file below', $card);
        $this->assertNotContains($asset->id, $this->pickerIds(), 'a missing image cannot be picked');
        $this->assertSame(MediaUsage::UNUSED, MediaUsage::report([$asset->fresh()])[$asset->id]['state']);

        // The suggested next step works: the same record and path get a file again.
        $this->post(route('admin.media.replace', $asset), ['file' => new UploadedFile($this->jpeg(), 'court-again.jpg', null, null, true)])->assertSessionHasNoErrors();
        $this->assertTrue(Storage::disk('public')->exists($asset->path));
        $this->assertContains($asset->id, $this->pickerIds());
        $this->assertSame(1, MediaAsset::withTrashed()->count());
    }
    public function test_missing_webp_copies_are_reported_and_the_original_is_used(): void
    {
        $asset = $this->uploaded();
        $recorded = count($asset->metadata['optimization']['variants']);
        Storage::disk('public')->deleteDirectory($asset->variantsDirectory());
        $asset->delete();
        $this->assertStringContainsString("WebP copies: 0 of {$recorded} present", $this->get(route('admin.media.trash'))->getContent());
        $this->post(route('admin.media.restore', $asset))->assertSessionHas('success', fn ($m) => str_contains($m, "{$recorded} of its WebP copies are missing") && str_contains($m, 'uses the original'));
        $this->assertNull(\App\Support\Media::responsive($asset->path)['webp'], 'no srcset points at missing copies');
    }
    public function test_replacement_history_is_kept_through_deletion_and_restore(): void
    {
        $asset = $this->uploaded();
        $this->post(route('admin.media.replace', $asset), ['file' => new UploadedFile($this->jpeg(), 'court-v2.jpg', null, null, true)])->assertSessionHasNoErrors();
        $history = Storage::disk('local')->allFiles('media-history/'.$asset->id);
        $this->assertNotEmpty($history);
        $this->delete(route('admin.media.destroy', $asset));
        $this->assertStringContainsString('Replacement history kept: 1 earlier version', $this->get(route('admin.media.trash'))->getContent());
        $this->post(route('admin.media.restore', $asset));
        $this->assertSame($history, Storage::disk('local')->allFiles('media-history/'.$asset->id));
    }
    public function test_a_restored_asset_is_protected_again_once_it_is_used(): void
    {
        $asset = $this->record('dd000001-0000-4000-8000-000000000001.jpg', true);
        $asset->delete();
        $this->post(route('admin.media.restore', $asset));
        $page = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'draft']);
        PageSection::create(['page_id' => $page->id, 'type' => 'hero', 'data' => ['image' => $asset->path]]);
        $this->assertSame(MediaUsage::IN_USE, MediaUsage::report([$asset->fresh()])[$asset->id]['state']);
        $this->delete(route('admin.media.destroy', $asset))->assertSessionHasErrors('media');
        $this->assertFalse($asset->fresh()->trashed());
    }
    public function test_restoring_clears_the_cached_public_site_data(): void
    {
        $asset = $this->record('ee000001-0000-4000-8000-000000000001.jpg', true);
        $asset->delete();
        Cache::put(PublicSiteData::CACHE_KEY, ['stale' => true], 600);
        $this->post(route('admin.media.restore', $asset));
        $this->assertFalse(Cache::has(PublicSiteData::CACHE_KEY));
    }
    public function test_a_record_is_never_restored_onto_a_path_another_asset_uses(): void
    {
        $old = $this->record('ff000001-0000-4000-8000-000000000001.jpg', true);
        $old->delete();
        $current = MediaAsset::create(['disk' => 'public', 'path' => $old->path, 'original_name' => 'current.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1]);
        $this->from(route('admin.media.trash'))->post(route('admin.media.restore', $old))->assertSessionHasErrors(['media' => 'Another library asset already uses this file path, so this one was not restored.']);
        $this->assertTrue(MediaAsset::withTrashed()->find($old->id)->trashed());
        $this->assertSame([$current->id], MediaAsset::pluck('id')->all());
    }

    // Invalid requests and permissions

    public function test_unknown_active_and_already_restored_assets_cannot_be_restored(): void
    {
        $active = $this->record('aa100001-0000-4000-8000-000000000001.jpg', true);
        $trashed = $this->record('aa100002-0000-4000-8000-000000000002.jpg', true);
        $trashed->delete();
        $this->post(route('admin.media.restore', 999999))->assertNotFound();
        $this->post('/admin/media/abc/restore')->assertNotFound();
        $this->post(route('admin.media.restore', $active))->assertNotFound();
        $this->post(route('admin.media.restore', $trashed))->assertRedirect();
        $this->post(route('admin.media.restore', $trashed))->assertNotFound();
        $this->get(route('admin.media.restore', $trashed))->assertClientError();
        $this->assertSame(2, MediaAsset::count());
    }
    public function test_signed_out_visitors_and_users_without_cms_access_are_refused_but_editors_may_restore(): void
    {
        $asset = $this->record('aa200001-0000-4000-8000-000000000001.jpg');
        $asset->delete();
        $this->app['auth']->forgetGuards();
        $this->get(route('admin.media.trash'))->assertRedirect(route('login'));
        $this->post(route('admin.media.restore', $asset))->assertRedirect(route('login'));
        $this->actingAs(User::create(['name' => 'Off', 'email' => 'off@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => false]));
        $this->get(route('admin.media.trash'))->assertForbidden();
        $this->post(route('admin.media.restore', $asset))->assertForbidden();
        $this->assertTrue(MediaAsset::withTrashed()->find($asset->id)->trashed());
        // Editors may delete media, so they may also undo a deletion.
        $this->actingAs(User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]));
        $this->get(route('admin.media.trash'))->assertOk();
        $this->post(route('admin.media.restore', $asset))->assertSessionHas('success');
        $this->assertFalse($asset->fresh()->trashed());
    }
}

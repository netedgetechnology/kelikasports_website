<?php
namespace Tests\Feature;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
/** Creating and editing pages, publication transitions, and the page sharing image. */
class PageManagementTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->root = sys_get_temp_dir().'/kelika-pages-test-'.bin2hex(random_bytes(6));
        mkdir("{$this->root}/public", 0755, true);
        config(['filesystems.disks.public.root' => "{$this->root}/public"]);
        Storage::forgetDisk('public');
        $this->actingAs(User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]));
    }
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
        parent::tearDown();
    }
    private function form(array $overrides = []): array
    {
        return $overrides + ['title' => 'Summer Camp', 'slug' => 'summer-camp', 'template' => 'standard', 'status' => 'draft', 'show_in_menu' => '1', 'sort_order' => 3, 'robots_directive' => 'index,follow'];
    }
    private function withHero(Page $page): Page
    {
        PageSection::create(['page_id' => $page->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => ['title' => $page->title, 'image' => '/images/kelika-wordmark.png']]);
        return $page;
    }
    private function draft(): Page
    {
        return Page::create($this->form(['status' => 'draft']));
    }
    private function media(string $mime = 'image/jpeg', bool $withFile = true, string $ext = 'jpg'): MediaAsset
    {
        $path = 'media/2026/10/'.bin2hex(random_bytes(8)).'.'.$ext;
        if ($withFile) Storage::disk('public')->put($path, 'x');
        return MediaAsset::create(['disk' => 'public', 'path' => $path, 'original_name' => basename($path), 'mime_type' => $mime, 'size_bytes' => 1]);
    }

    // Creating

    public function test_a_page_is_created_as_a_draft_with_its_settings(): void
    {
        $this->post(route('admin.pages.store'), $this->form(['seo_title' => 'Camp 2026', 'seo_description' => 'Holiday camp']))->assertSessionHasNoErrors();
        $page = Page::sole();
        $this->assertSame(['Summer Camp', 'summer-camp', 'draft', 3, true, 'Camp 2026', 'Holiday camp'], [$page->title, $page->slug, $page->status, $page->sort_order, $page->show_in_menu, $page->seo_title, $page->seo_description]);
        $this->assertNull($page->published_at);
        $this->assertSame(User::first()->id, $page->created_by);
        $this->get('/summer-camp')->assertNotFound();
    }
    public function test_new_pages_cannot_start_published_or_scheduled_and_invalid_settings_are_rejected(): void
    {
        foreach (['published', 'scheduled'] as $status) $this->post(route('admin.pages.store'), $this->form(['status' => $status, 'published_at' => now()->addDay()->format('Y-m-d\TH:i')]))->assertSessionHasErrors('status');
        $this->post(route('admin.pages.store'), $this->form(['title' => '', 'slug' => 'Bad Slug', 'template' => 'fancy', 'robots_directive' => 'all']))->assertSessionHasErrors(['title', 'slug', 'template', 'robots_directive']);
        $this->post(route('admin.pages.store'), $this->form(['slug' => 'admin']))->assertSessionHasErrors('slug');
        $this->post(route('admin.pages.store'), $this->form());
        $this->post(route('admin.pages.store'), $this->form(['title' => 'Again']))->assertSessionHasErrors('slug');
        $this->assertSame(1, Page::count());
    }

    // Editing and publishing

    public function test_editing_saves_settings_and_records_the_previous_version(): void
    {
        $page = $this->draft();
        $this->put(route('admin.pages.update', $page), $this->form(['title' => 'Summer Camp 2026', 'seo_description' => 'Updated']))->assertSessionHasNoErrors();
        $this->assertSame(['Summer Camp 2026', 'Updated'], [$page->fresh()->title, $page->fresh()->seo_description]);
        $revision = PageRevision::sole();
        $this->assertSame(['Before update', 'Summer Camp', 'draft'], [$revision->revision_label, $revision->snapshot['page']['title'], $revision->snapshot['page']['status']]);
    }
    public function test_publishing_needs_a_visible_hero_image(): void
    {
        $page = $this->draft();
        $this->post(route('admin.pages.publish', $page))->assertSessionHasErrors(['publish' => 'Add an image to a visible Hero section before publishing this page.']);
        $this->put(route('admin.pages.update', $page), $this->form(['status' => 'published']))->assertSessionHasErrors('publish');
        PageSection::create(['page_id' => $page->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => false, 'data' => ['image' => '/images/kelika-wordmark.png']]);
        $this->post(route('admin.pages.publish', $page))->assertSessionHasErrors('publish');
        PageSection::create(['page_id' => $page->id, 'type' => 'hero', 'sort_order' => 2, 'is_visible' => true, 'data' => ['image' => 'media/2026/10/missing.jpg']]);
        $this->post(route('admin.pages.publish', $page))->assertSessionHasErrors(['publish' => 'The selected Hero image file is missing from storage. Re-select or upload the image before publishing.']);
        $this->assertSame('draft', $page->fresh()->status);
    }
    public function test_draft_published_and_draft_again(): void
    {
        $page = $this->withHero($this->draft());
        $this->post(route('admin.pages.publish', $page))->assertSessionHas('success', 'Page published.');
        $page->refresh();
        $this->assertSame(['published', 'published'], [$page->status, $page->publicationState()]);
        $firstPublished = $page->published_at;
        $this->get('/summer-camp')->assertOk();

        $this->travel(2)->days();
        $this->post(route('admin.pages.publish', $page));
        $this->assertEquals($firstPublished, $page->fresh()->published_at, 'publishing a live page keeps its date');

        $this->post(route('admin.pages.unpublish', $page))->assertSessionHas('success', 'Page changed to draft and removed from public view.');
        $this->assertSame('draft', $page->fresh()->status);
        $this->get('/summer-camp')->assertNotFound();
        $this->assertSame(['Before unpublishing', 'Before publishing', 'Before publishing'], PageRevision::latest('id')->pluck('revision_label')->all());

        $this->put(route('admin.pages.update', $page), $this->form(['status' => 'published']))->assertSessionHasNoErrors();
        $this->assertTrue($page->fresh()->isLive());
        $this->assertTrue($page->fresh()->published_at->isAfter($firstPublished), 'republishing starts a new publication date');
    }
    public function test_renaming_a_live_page_keeps_its_old_address_working(): void
    {
        $page = $this->withHero($this->draft());
        $this->post(route('admin.pages.publish', $page));
        $this->put(route('admin.pages.update', $page), $this->form(['slug' => 'holiday-camp', 'status' => 'published']))->assertSessionHasNoErrors();
        $this->get('/summer-camp')->assertRedirect('/holiday-camp');
        $this->get('/holiday-camp')->assertOk();
    }

    // Sharing image

    public function test_an_available_library_image_can_be_the_sharing_image(): void
    {
        $page = $this->draft();
        $png = $this->media('image/png', true, 'png');
        $this->put(route('admin.pages.update', $page), $this->form(['og_media_id' => $png->id]))->assertSessionHasNoErrors();
        $this->assertSame($png->id, $page->fresh()->og_media_id);
        $this->put(route('admin.pages.update', $page), $this->form(['og_media_id' => '']))->assertSessionHasNoErrors();
        $this->assertNull($page->fresh()->og_media_id, 'it can be cleared to use the global image');
    }
    public function test_deleted_missing_non_image_and_unknown_media_are_rejected(): void
    {
        $page = $this->draft();
        $deleted = $this->media();
        $deleted->delete();
        $cases = ['deleted' => $deleted->id, 'missing file' => $this->media('image/jpeg', false)->id, 'pdf' => $this->media('application/pdf', true, 'pdf')->id, 'unknown id' => 999999, 'not a number' => 'abc', 'zero' => 0];
        foreach ($cases as $case => $id) {
            $this->put(route('admin.pages.update', $page), $this->form(['og_media_id' => $id]))->assertSessionHasErrors('og_media_id', null, 'default');
            $this->assertNull($page->fresh()->og_media_id, $case);
        }
        $this->post(route('admin.pages.store'), $this->form(['slug' => 'other', 'og_media_id' => $cases['pdf']]))->assertSessionHasErrors(['og_media_id' => 'Choose an available image from the media library as the sharing image, or leave it empty to use the global image.']);
    }
    public function test_the_editor_offers_only_available_images_and_flags_an_unavailable_choice(): void
    {
        $page = $this->draft();
        $good = $this->media();
        $missing = $this->media('image/jpeg', false);
        $pdf = $this->media('application/pdf', true, 'pdf');
        $html = $this->get(route('admin.pages.edit', $page))->getContent();
        $this->assertStringContainsString('<option value="'.$good->id.'"', $html);
        $this->assertStringNotContainsString('<option value="'.$missing->id.'"', $html);
        $this->assertStringNotContainsString('<option value="'.$pdf->id.'"', $html);
        $this->assertStringNotContainsString('data-sharing-image-unavailable', $html);
        // A choice saved earlier whose file has since gone is pointed out.
        $page->update(['og_media_id' => $good->id]);
        Storage::disk('public')->delete($good->path);
        $this->get(route('admin.pages.edit', $page))->assertSee('data-sharing-image-unavailable', false);
        $this->get(route('admin.pages.create'))->assertOk()->assertDontSee('data-sharing-image-unavailable', false);
    }
}

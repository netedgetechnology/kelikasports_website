<?php
namespace Tests\Feature;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ArchiveRestoreTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]);
        $this->actingAs($this->admin);
    }
    private function editor(): User
    {
        return User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
    }
    private function sport(array $attributes = []): ContentItem
    {
        return ContentItem::create($attributes + ['type' => 'sport', 'title' => 'Badminton', 'slug' => 'badminton', 'status' => 'published', 'data' => ['summary' => 'Kept text', 'image' => '/images/home/hero-player.jpg']]);
    }

    // Content items
    public function test_an_archived_content_item_is_listed_and_can_be_restored_as_a_draft(): void
    {
        $sport = $this->sport();
        $this->from(route('admin.content.index', 'sport'))->delete(route('admin.content.destroy', ['sport', $sport]))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($sport);
        $list = $this->get(route('admin.content.index', 'sport'))->assertOk();
        $list->assertSee('Archived sport items')->assertSee(route('admin.content.restore', ['sport', $sport]), false);

        $this->from(route('admin.content.index', 'sport'))->post(route('admin.content.restore', ['sport', $sport]))
            ->assertRedirect(route('admin.content.index', 'sport'))->assertSessionHas('success');
        $restored = $sport->fresh();
        $this->assertNotSoftDeleted($restored);
        $this->assertSame('draft', $restored->status, 'never back on the website without being published');
        $this->assertSame(['badminton', ['summary' => 'Kept text', 'image' => '/images/home/hero-player.jpg']], [$restored->slug, $restored->data], 'content kept');
        $this->assertSame($this->admin->id, $restored->updated_by);
        $this->get(route('admin.content.index', 'sport'))->assertDontSee('Archived sport items');
    }
    public function test_editors_can_restore_content_items_they_can_archive(): void
    {
        $sport = $this->sport();
        $sport->delete();
        $this->actingAs($this->editor())->post(route('admin.content.restore', ['sport', $sport]))->assertSessionHas('success');
        $this->assertNotSoftDeleted($sport);
    }
    public function test_restore_rejects_the_wrong_type_and_items_that_are_not_archived(): void
    {
        $sport = $this->sport();
        $this->post(route('admin.content.restore', ['sport', $sport]))->assertNotFound();
        $sport->delete();
        $this->post(route('admin.content.restore', ['event', $sport]))->assertNotFound();
        $this->post(route('admin.content.restore', ['unknown', $sport]))->assertNotFound();
        $this->assertSoftDeleted($sport);
    }
    public function test_restoring_an_item_unlocks_media_it_was_keeping_in_use(): void
    {
        $asset = MediaAsset::create(['disk' => 'public', 'path' => 'media/2026/10/team-photo.jpg', 'original_name' => 'team.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1]);
        $coach = ContentItem::create(['type' => 'coach', 'title' => 'Head Coach', 'slug' => 'head-coach', 'status' => 'published', 'data' => ['image' => $asset->path]]);
        $coach->delete();
        // Archived items still protect their media, and the archive now lets the administrator act on that.
        $this->from(route('admin.media.index'))->delete(route('admin.media.destroy', $asset))->assertSessionHasErrors('media');
        $this->post(route('admin.content.restore', ['coach', $coach]));
        $this->put(route('admin.content.update', ['coach', $coach]), ['title' => 'Head Coach', 'slug' => 'head-coach', 'status' => 'draft', 'data_json' => '{"image":""}'])->assertSessionHasNoErrors();
        $this->from(route('admin.media.index'))->delete(route('admin.media.destroy', $asset))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($asset);
    }

    // Pages
    public function test_an_administrator_can_archive_a_page_from_its_editor_and_restore_it_as_a_draft(): void
    {
        $page = Page::create(['title' => 'Camp', 'slug' => 'camp', 'status' => 'published', 'published_at' => now()->subDay()]);
        PageSection::create(['page_id' => $page->id, 'type' => 'rich_text', 'data' => ['title' => 'Summer camp', 'body' => 'Kept']]);
        $this->get(route('admin.pages.edit', $page))->assertSee('Archive page');
        $this->delete(route('admin.pages.destroy', $page))->assertRedirect(route('admin.pages.index'));
        $this->assertSoftDeleted($page);
        $this->get('/camp')->assertNotFound();
        $this->get(route('admin.pages.index'))->assertSee('Archived pages')->assertSee(route('admin.pages.restore', $page), false);

        $this->post(route('admin.pages.restore', $page))->assertRedirect(route('admin.pages.edit', $page))->assertSessionHas('success');
        $restored = $page->fresh();
        $this->assertNotSoftDeleted($restored);
        $this->assertSame('draft', $restored->status);
        $this->assertSame('Kept', $restored->sections()->first()->data['body'], 'sections kept');
        $this->get('/camp')->assertNotFound(); // still hidden until published
    }
    public function test_core_pages_cannot_be_archived_and_the_button_is_not_offered(): void
    {
        $home = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'draft']);
        $this->get(route('admin.pages.edit', $home))->assertDontSee('Archive page');
        $this->delete(route('admin.pages.destroy', $home))->assertStatus(422);
        $this->assertNotSoftDeleted($home);
    }
    public function test_editors_cannot_see_or_restore_archived_pages(): void
    {
        $page = Page::create(['title' => 'Camp', 'slug' => 'camp', 'status' => 'draft']);
        $page->delete();
        $this->actingAs($this->editor());
        $this->get(route('admin.pages.index'))->assertOk()->assertDontSee('Archived pages');
        $this->get(route('admin.pages.edit', Page::create(['title' => 'Other', 'slug' => 'other', 'status' => 'draft'])))->assertDontSee('Archive page');
        $this->post(route('admin.pages.restore', $page))->assertForbidden();
        $this->assertSoftDeleted($page);
    }
    public function test_restoring_a_page_that_is_not_archived_is_rejected(): void
    {
        $page = Page::create(['title' => 'Camp', 'slug' => 'camp', 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->post(route('admin.pages.restore', $page))->assertNotFound();
        $this->assertSame('published', $page->fresh()->status);
    }
}

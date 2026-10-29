<?php
namespace Tests\Feature;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/**
 * Home and Contact stay live and keep their addresses. (Unpublishing, changing either to Draft or Scheduled,
 * archiving and restoring a draft revision are covered in ScheduledPublishingTest::test_home_and_contact_stay_live.)
 */
class CorePageSafeguardsTest extends TestCase
{
    use RefreshDatabase;
    private Page $home;
    private Page $contact;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        $this->home = $this->live('home', 'Home');
        $this->contact = $this->live('contact', 'Contact');
    }
    private function live(string $slug, string $title): Page
    {
        $page = Page::create(['title' => $title, 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subMonth(), 'robots_directive' => 'index,follow', 'template' => 'standard', 'show_in_menu' => true]);
        PageSection::create(['page_id' => $page->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => ['title' => $title, 'image' => '/images/kelika-wordmark.png']]);
        return $page;
    }
    private function save(Page $page, array $overrides)
    {
        return $this->from(route('admin.pages.edit', $page))->put(route('admin.pages.update', $page), $overrides + [
            'title' => $page->title, 'slug' => $page->slug, 'template' => 'standard', 'status' => 'published', 'show_in_menu' => '1', 'sort_order' => 0, 'robots_directive' => 'index,follow',
        ]);
    }
    private function restore(Page $page, array $snapshotPage)
    {
        $revision = PageRevision::create(['page_id' => $page->id, 'revision_label' => 'Test', 'snapshot' => ['page' => $snapshotPage]]);
        return $this->from(route('admin.pages.edit', $page))->post(route('admin.pages.revisions.restore', [$page, $revision]));
    }
    private function assertStillLive(Page $page, string $slug): void
    {
        $fresh = $page->fresh();
        $this->assertSame([$slug, 'published', true], [$fresh->slug, $fresh->status, $fresh->isLive()]);
    }

    public function test_neither_address_can_be_changed_in_the_settings_form(): void
    {
        $this->save($this->home, ['slug' => 'welcome'])->assertSessionHasErrors(['slug' => 'The homepage slug is reserved and cannot be changed.']);
        $this->save($this->contact, ['slug' => 'contact-us'])->assertSessionHasErrors(['slug' => 'The Contact page address /contact is fixed and cannot be changed.']);
        $this->assertStillLive($this->home, 'home');
        $this->assertStillLive($this->contact, 'contact');
        $this->flushSession();
        $this->get(route('admin.pages.edit', $this->contact))->assertSee('This page\'s address is fixed.', false)->assertSee('id="page-slug" name="slug" value="contact" required pattern="[a-z0-9]+(-[a-z0-9]+)*" readonly', false);
    }
    public function test_a_renamed_contact_page_could_otherwise_lose_its_protection(): void
    {
        // The protection follows the address, so keeping the address is what keeps Contact protected.
        $this->save($this->contact, ['slug' => 'contact-us']);
        $this->post(route('admin.pages.unpublish', $this->contact))->assertSessionHasErrors('status');
        $this->delete(route('admin.pages.destroy', $this->contact))->assertStatus(422);
        $this->assertStillLive($this->contact, 'contact');
    }
    public function test_revisions_cannot_change_an_address_or_take_a_core_page_offline(): void
    {
        $this->restore($this->contact, ['slug' => 'contact-us'])->assertSessionHasErrors('revision');
        $this->assertStringContainsString('the Contact page address /contact cannot change.', session('errors')->first('revision'));
        $this->restore($this->home, ['slug' => 'welcome'])->assertSessionHasErrors('revision');
        $this->assertStringContainsString('the homepage address cannot change.', session('errors')->first('revision'));
        $this->restore($this->contact, ['status' => 'scheduled', 'published_at' => now()->addWeek()->format('Y-m-d H:i:s')])->assertSessionHasErrors('revision');
        $this->assertStringContainsString('the Contact page must stay published.', session('errors')->first('revision'));
        $this->restore($this->home, ['status' => 'published', 'published_at' => now()->addWeek()->format('Y-m-d H:i:s')])->assertSessionHasErrors('revision');
        $this->assertStillLive($this->home, 'home');
        $this->assertStillLive($this->contact, 'contact');
        $this->get('/')->assertOk();
        $this->get('/contact')->assertOk();
    }
    public function test_a_future_date_cannot_be_given_to_a_live_core_page(): void
    {
        $this->save($this->home, ['status' => 'published', 'published_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertSessionHasErrors('published_at');
        $this->assertStillLive($this->home, 'home');
    }
    public function test_ordinary_editing_still_works_and_is_recorded(): void
    {
        $this->save($this->contact, ['title' => 'Contact KELIKA', 'seo_title' => 'Get in touch', 'robots_directive' => 'index,follow'])->assertSessionHasNoErrors();
        $this->assertSame(['Contact KELIKA', 'Get in touch'], [$this->contact->fresh()->title, $this->contact->fresh()->seo_title]);
        $this->post(route('admin.pages.publish', $this->home))->assertSessionHas('success', 'Page published.');
        $this->assertStillLive($this->home, 'home');
        // Restoring an older, still-live version is allowed.
        $older = PageRevision::where('page_id', $this->contact->id)->latest('id')->first();
        $this->post(route('admin.pages.revisions.restore', [$this->contact, $older]))->assertSessionHasNoErrors();
        $this->assertSame('Contact', $this->contact->fresh()->title);
        $this->assertStillLive($this->contact, 'contact');
    }
    public function test_editors_get_the_same_protection(): void
    {
        $this->actingAs(User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]));
        $this->save($this->home, ['status' => 'draft'])->assertSessionHasErrors('status');
        $this->post(route('admin.pages.unpublish', $this->contact))->assertSessionHasErrors('status');
        $this->delete(route('admin.pages.destroy', $this->home))->assertForbidden();
        $this->assertStillLive($this->home, 'home');
        $this->assertStillLive($this->contact, 'contact');
    }
    public function test_a_core_page_that_is_not_live_yet_can_be_published(): void
    {
        $this->home->update(['status' => 'draft']);
        $this->post(route('admin.pages.publish', $this->home))->assertSessionHasNoErrors();
        $this->assertStillLive($this->home, 'home');
    }
}

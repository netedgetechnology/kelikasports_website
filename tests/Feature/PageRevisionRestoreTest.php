<?php
namespace Tests\Feature;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use Tests\TestCase;
class PageRevisionRestoreTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private Page $page;
    private PageSection $intro;
    private PageSection $faq;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]);
        $this->actingAs($this->admin);
        $this->page = Page::create(['title' => 'Camp', 'slug' => 'camp', 'status' => 'draft', 'seo_title' => 'Original SEO', 'canonical_url' => 'https://kelikasports.com/camp', 'robots_directive' => 'index,follow', 'show_in_menu' => true]);
        $this->intro = PageSection::create(['page_id' => $this->page->id, 'type' => 'rich_text', 'admin_label' => 'Intro', 'sort_order' => 1, 'is_visible' => true, 'data' => ['title' => 'Original intro', 'body' => 'First text']]);
        $this->faq = PageSection::create(['page_id' => $this->page->id, 'type' => 'faq', 'admin_label' => 'FAQ', 'sort_order' => 2, 'is_visible' => true, 'data' => ['title' => 'Questions', 'items' => [['question' => 'Q1', 'answer' => 'A1']]]]);
    }
    private function settings(array $overrides = []): array
    {
        return $overrides + ['title' => 'Camp', 'slug' => 'camp', 'template' => 'standard', 'status' => 'draft', 'seo_title' => 'Original SEO', 'canonical_url' => 'https://kelikasports.com/camp', 'robots_directive' => 'index,follow', 'show_in_menu' => 1];
    }
    private function restore(PageRevision|int $revision, ?Page $page = null)
    {
        $page ??= $this->page;
        return $this->from(route('admin.pages.edit', $page))->post(route('admin.pages.revisions.restore', [$page, $revision]));
    }
    /** Real edits through the CMS, each recording a "before" revision. Returns the revision taken before the first edit. */
    private function makeHistory(): PageRevision
    {
        $this->put(route('admin.pages.sections.update', [$this->page, $this->intro]), ['admin_label' => 'Intro (edited)', 'is_visible' => 1, 'title' => 'Edited intro', 'body' => 'Second text'])->assertSessionHasNoErrors();
        $first = PageRevision::orderBy('id')->firstOrFail();
        $this->post(route('admin.pages.sections.store', $this->page), ['type' => 'cta', 'admin_label' => 'Added later'])->assertSessionHasNoErrors();
        $this->delete(route('admin.pages.sections.destroy', [$this->page, $this->faq]))->assertSessionHasNoErrors();
        $this->put(route('admin.pages.update', $this->page), $this->settings(['title' => 'Summer Camp', 'seo_title' => 'Changed SEO', 'robots_directive' => 'noindex,follow']))->assertSessionHasNoErrors();
        return $first;
    }

    public function test_restoring_an_older_revision_brings_back_its_settings_and_sections_on_the_same_page(): void
    {
        $first = $this->makeHistory();
        $pageCount = Page::count();
        $this->restore($first)->assertRedirect(route('admin.pages.edit', $this->page))->assertSessionHasNoErrors()->assertSessionHas('success');

        $page = $this->page->fresh();
        $this->assertSame([$this->page->id, $pageCount], [$page->id, Page::count()], 'same page, no duplicate');
        $this->assertSame(['Camp', 'Original SEO', 'index,follow', 'https://kelikasports.com/camp'], [$page->title, $page->seo_title, $page->robots_directive, $page->canonical_url]);
        $sections = $page->sections()->get();
        $this->assertSame(['Intro', 'FAQ'], $sections->pluck('admin_label')->all(), 'later section removed, deleted section back');
        $this->assertSame($this->intro->id, $sections[0]->id, 'an existing section keeps its ID');
        $this->assertSame(['title' => 'Original intro', 'body' => 'First text'], $sections[0]->data);
        $this->assertSame(['title' => 'Questions', 'items' => [['question' => 'Q1', 'answer' => 'A1']]], $sections[1]->data, 'a deleted section is recreated with its data');
        $this->assertSame($page->id, $sections[1]->page_id);
        $this->assertSame([1, 2], $sections->pluck('sort_order')->all());
    }
    public function test_the_state_before_a_restore_is_saved_so_the_restore_can_be_undone(): void
    {
        $first = $this->makeHistory();
        $before = PageRevision::count();
        $this->restore($first);
        $this->assertSame($before + 1, PageRevision::count());
        $saved = PageRevision::latest('id')->first();
        $this->assertStringStartsWith('Before restoring the version from', $saved->revision_label);
        $this->assertSame($this->admin->id, $saved->user_id);
        $this->assertSame('Summer Camp', $saved->snapshot['page']['title']);
        $this->assertSame(['Intro (edited)', 'Added later'], array_column($saved->snapshot['sections'], 'admin_label'));

        $this->restore($saved)->assertSessionHasNoErrors();
        $page = $this->page->fresh();
        $this->assertSame(['Summer Camp', 'Changed SEO'], [$page->title, $page->seo_title], 'undo brings the restored-over state back');
        $this->assertSame(['Intro (edited)', 'Added later'], $page->sections()->pluck('admin_label')->all());
    }
    public function test_only_what_the_revision_recorded_is_restored(): void
    {
        // Older revisions recorded six settings and some may hold no sections.
        $legacy = PageRevision::create(['page_id' => $this->page->id, 'user_id' => null, 'revision_label' => 'Legacy', 'snapshot' => ['page' => ['title' => 'Old title', 'seo_title' => 'Old SEO', 'show_in_menu' => false, 'og_title' => 'Not restorable']]]);
        $this->put(route('admin.pages.update', $this->page), $this->settings(['canonical_url' => 'https://kelikasports.com/new', 'robots_directive' => 'noindex,nofollow']));
        $sectionsBefore = $this->page->sections()->get()->toArray();
        $this->restore($legacy)->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'did not record') && str_contains($m, 'canonical url') && str_contains($m, 'did not record sections'));
        $page = $this->page->fresh();
        $this->assertSame(['Old title', 'Old SEO'], [$page->title, $page->seo_title]);
        $this->assertSame(['https://kelikasports.com/new', 'noindex,nofollow', true, null], [$page->canonical_url, $page->robots_directive, $page->show_in_menu, $page->og_title], 'unrecorded fields untouched');
        $this->assertEquals($sectionsBefore, $page->sections()->get()->toArray(), 'sections untouched when not recorded');
    }
    public function test_missing_foreign_and_invalid_revisions_change_nothing(): void
    {
        $other = Page::create(['title' => 'Other', 'slug' => 'other', 'status' => 'draft']);
        $foreign = PageRevision::create(['page_id' => $other->id, 'revision_label' => 'Other page', 'snapshot' => ['page' => ['title' => 'Hijack']]]);
        $this->restore(999999)->assertNotFound();
        $this->restore($foreign)->assertNotFound();

        $count = PageRevision::count();
        $invalid = [
            'empty snapshot' => [],
            'unknown section type' => ['sections' => [['type' => 'evil', 'data' => []]]],
            'section without data' => ['page' => ['title' => 'X'], 'sections' => [['type' => 'faq']]],
            'invalid setting' => ['page' => ['template' => 'nonsense']],
            'slug taken by another page' => ['page' => ['slug' => 'other']],
            'reserved slug' => ['page' => ['slug' => 'admin']],
        ];
        foreach ($invalid as $case => $snapshot) {
            $revision = PageRevision::create(['page_id' => $this->page->id, 'revision_label' => $case, 'snapshot' => $snapshot]);
            $count++;
            $before = [$this->page->fresh()->toArray(), $this->page->sections()->get()->toArray()];
            $this->restore($revision)->assertRedirect(route('admin.pages.edit', $this->page))->assertSessionHasErrors('revision');
            $this->assertEquals($before, [$this->page->fresh()->toArray(), $this->page->sections()->get()->toArray()], $case);
            $this->assertSame($count, PageRevision::count(), "$case: no extra revision");
        }
    }
    public function test_a_published_page_cannot_be_restored_into_an_unpublishable_state(): void
    {
        $home = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()->subDay()]);
        $hero = PageSection::create(['page_id' => $home->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => ['image' => 'https://images.unsplash.com/photo-1?w=1800']]);
        $noHero = PageRevision::create(['page_id' => $home->id, 'revision_label' => 'Before hero', 'snapshot' => ['page' => ['status' => 'published'], 'sections' => []]]);
        $renamed = PageRevision::create(['page_id' => $home->id, 'revision_label' => 'Other slug', 'snapshot' => ['page' => ['slug' => 'welcome']]]);
        $this->restore($noHero, $home)->assertSessionHasErrors(['revision' => 'The version from '.$noHero->created_at->format('d M Y H:i').' cannot be restored: Add an image to a visible Hero section before publishing this page. Nothing was changed.']);
        $this->assertSame([$hero->id], $home->sections()->pluck('id')->all(), 'hero kept: the whole restore rolled back');
        $this->assertSame(0, PageRevision::where('revision_label', 'like', 'Before restoring%')->count(), 'the pre-restore snapshot rolled back too');
        $this->restore($renamed, $home)->assertSessionHasErrors('revision');
        $this->assertSame('home', $home->fresh()->slug);
    }
    public function test_restoring_a_published_page_to_an_older_address_redirects_the_current_one(): void
    {
        $page = Page::create(['title' => 'Programs', 'slug' => 'programs', 'status' => 'published', 'published_at' => now()->subDay()]);
        PageSection::create(['page_id' => $page->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => ['image' => 'https://images.unsplash.com/photo-1?w=1800']]);
        $this->put(route('admin.pages.update', $page), $this->settings(['title' => 'Programs', 'slug' => 'courses', 'status' => 'published', 'canonical_url' => '']))->assertSessionHasNoErrors();
        $this->restore(PageRevision::where('page_id', $page->id)->latest('id')->first(), $page)->assertSessionHasNoErrors();
        $this->assertSame('programs', $page->fresh()->slug);
        $this->assertDatabaseHas('redirects', ['from_path' => '/courses', 'to_path' => '/programs', 'is_active' => true]);
        $this->assertFalse((bool) Redirect::where('from_path', '/programs')->value('is_active'), 'no loop back to the old address');
        $this->get('/programs')->assertOk();
        $this->get('/courses')->assertRedirect('/programs');
    }
    public function test_a_failure_part_way_through_rolls_everything_back(): void
    {
        Exceptions::fake();
        $first = $this->makeHistory();
        $before = [$this->page->fresh()->toArray(), $this->page->sections()->get()->toArray(), PageRevision::count()];
        PageSection::creating(fn () => throw new RuntimeException('Simulated database failure')); // fails while recreating the deleted FAQ
        $this->restore($first)->assertSessionHasErrors(['revision' => 'The revision could not be restored, so the page was left exactly as it was. Please try again.']);
        $this->assertEquals($before, [$this->page->fresh()->toArray(), $this->page->sections()->get()->toArray(), PageRevision::count()]);
        Exceptions::assertReported(RuntimeException::class);
    }
    public function test_the_editor_lists_revisions_with_author_time_and_a_confirmed_restore_action(): void
    {
        $first = $this->makeHistory();
        $html = $this->get(route('admin.pages.edit', $this->page))->assertOk()->getContent();
        $this->assertStringContainsString('Saved revisions', $html);
        $this->assertStringContainsString('Admin · '.$first->created_at->format('d M Y H:i'), $html);
        $this->assertStringContainsString(route('admin.pages.revisions.restore', [$this->page, $first]), $html);
        $this->assertStringContainsString('data-confirm="Restore the version from '.$first->created_at->format('d M Y H:i'), $html);
        $this->assertStringContainsString('2 sections', $html);
    }
    public function test_editors_keep_their_existing_ability_to_edit_pages_including_restoring_revisions(): void
    {
        $first = $this->makeHistory();
        $this->actingAs(User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]));
        $this->restore($first)->assertSessionHasNoErrors();
        $this->assertSame('Camp', $this->page->fresh()->title);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->post(route('admin.pages.revisions.restore', [$this->page, $first]))->assertRedirect(route('login'));
    }
}

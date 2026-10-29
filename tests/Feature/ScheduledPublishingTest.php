<?php
namespace Tests\Feature;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\User;
use App\Support\PublicSiteData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
/** Scheduling a page in the CMS, its visibility before and after its time, and kelika:publish-scheduled. */
class ScheduledPublishingTest extends TestCase
{
    use RefreshDatabase;
    private const TZ = 'Asia/Kolkata';
    private User $admin;
    private Page $camp;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-01 09:00:00', self::TZ));
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]);
        $this->actingAs($this->admin);
        foreach (['home' => 'Home', 'contact' => 'Contact'] as $slug => $title) $this->page($slug, $title, 'published', now()->subMonth());
        $this->camp = $this->page('summer-camp', 'Summer Camp', 'draft', null);
        $menu = Menu::create(['name' => 'Main', 'location' => 'main', 'is_active' => true]);
        $menu->menuItems()->create(['label' => 'Camp link', 'url' => '/summer-camp', 'target' => '_self', 'sort_order' => 1, 'is_visible' => true]);
    }
    private function page(string $slug, string $title, string $status, ?Carbon $publishedAt): Page
    {
        $page = Page::create(['title' => $title, 'slug' => $slug, 'status' => $status, 'published_at' => $publishedAt, 'show_in_menu' => true, 'robots_directive' => 'index,follow', 'template' => 'standard']);
        PageSection::create(['page_id' => $page->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => ['title' => $title, 'image' => '/images/kelika-wordmark.png']]);
        return $page;
    }
    /** The page settings form as the editor submits it. */
    private function settings(Page $page, array $overrides = [])
    {
        return $this->from(route('admin.pages.edit', $page))->put(route('admin.pages.update', $page), $overrides + [
            'title' => $page->title, 'slug' => $page->slug, 'template' => 'standard', 'status' => $page->status, 'show_in_menu' => '1',
            'sort_order' => 0, 'robots_directive' => 'index,follow', '_form' => 'page-settings',
        ]);
    }
    private function schedule(string $when = '2026-11-05T09:30')
    {
        return $this->settings($this->camp, ['status' => 'scheduled', 'published_at' => $when]);
    }
    private function asVisitor(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }
    /** Every public place the camp page can appear, as seen by a signed-out visitor right now. */
    private function publicView(): array
    {
        $this->asVisitor();
        Cache::flush();
        return [
            'page' => $this->get('/summer-camp')->status(),
            'menu' => str_contains($this->get('/contact')->getContent(), 'href="/summer-camp"'),
            'search' => str_contains($this->get('/search?q=summer')->getContent(), 'Summer Camp'),
            'sitemap' => str_contains($this->get('/sitemap.xml')->getContent(), '/summer-camp</loc>'),
        ];
    }
    private function runScheduler(): string
    {
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('kelika:publish-scheduled'));
        return \Illuminate\Support\Facades\Artisan::output();
    }
    private const HIDDEN = ['page' => 404, 'menu' => false, 'search' => false, 'sitemap' => false];
    private const SHOWN = ['page' => 200, 'menu' => true, 'search' => true, 'sitemap' => true];

    public function test_a_draft_can_be_scheduled_for_a_future_time(): void
    {
        $this->schedule()->assertRedirect(route('admin.pages.edit', $this->camp))
            ->assertSessionHas('success', 'Page settings saved. The page is scheduled to go live on 05 Nov 2026 at 09:30 (Asia/Kolkata).');
        $this->camp->refresh();
        $this->assertSame([Page::SCHEDULED, '2026-11-05 09:30:00'], [$this->camp->status, DB::table('pages')->where('id', $this->camp->id)->value('published_at')]);
        $this->assertSame('Scheduled · 05 Nov 2026, 09:30', $this->camp->publicationLabel());
        $this->get(route('admin.pages.edit', $this->camp))->assertSee('data-publication-state="scheduled">Scheduled · 05 Nov 2026, 09:30', false)
            ->assertSee('value="2026-11-05T09:30"', false)->assertSee('Publish now')->assertSee('Cancel schedule');
        $this->get(route('admin.pages.index'))->assertSee('Scheduled · 05 Nov 2026, 09:30');
        $this->assertSame(1, $this->get(route('admin.dashboard'))->viewData('scheduledPages'));
    }
    public function test_missing_invalid_and_past_dates_are_rejected_without_changes(): void
    {
        $before = $this->camp->fresh()->getAttributes();
        $this->schedule('')->assertSessionHasErrors(['published_at' => 'Choose the date and time this page should go live.']);
        $this->schedule('next tuesday-ish')->assertSessionHasErrors('published_at');
        $this->schedule('2026-11-01T09:00')->assertSessionHasErrors(['published_at' => 'Choose a time in the future (Asia/Kolkata time), or choose Published to publish the page now.']);
        $this->schedule('2026-10-01T10:00')->assertSessionHasErrors('published_at');
        $this->settings($this->camp, ['status' => 'published', 'published_at' => '2026-11-05T09:30'])->assertSessionHasErrors(['published_at' => 'To publish this page later, choose Scheduled. Published pages go live straight away.']);
        $this->settings($this->camp, ['status' => 'live'])->assertSessionHasErrors('status');
        $this->assertSame($before, $this->camp->fresh()->getAttributes());
    }
    public function test_a_page_needs_a_hero_image_before_it_can_be_scheduled(): void
    {
        $this->camp->sections()->update(['data' => json_encode(['title' => 'Camp'])]);
        $this->schedule()->assertSessionHasErrors(['publish' => 'Add an image to a visible Hero section before publishing this page.']);
        $this->assertSame(Page::DRAFT, $this->camp->fresh()->status);
    }
    public function test_a_scheduled_page_stays_hidden_everywhere_until_its_time_then_appears_even_before_the_scheduler_runs(): void
    {
        $this->schedule();
        $this->assertSame(self::HIDDEN, $this->publicView());
        $this->travelTo(Carbon::parse('2026-11-05 09:29:59', self::TZ));
        $this->assertSame(self::HIDDEN, $this->publicView(), 'one second early is still hidden');
        $this->travelTo(Carbon::parse('2026-11-05 09:30:00', self::TZ));
        $this->assertSame(self::SHOWN, $this->publicView(), 'visible on time, whether or not cron has run');
        $this->assertSame(Page::SCHEDULED, $this->camp->fresh()->status);
    }
    public function test_the_scheduler_records_a_due_page_as_published_once(): void
    {
        $this->schedule();
        $this->assertStringContainsString('No scheduled pages are due.', $this->runScheduler());
        $this->assertSame(Page::SCHEDULED, $this->camp->fresh()->status, 'nothing happens early');

        $this->travelTo(Carbon::parse('2026-11-05 09:31:00', self::TZ));
        $revisions = PageRevision::count();
        $output = $this->runScheduler();
        $this->assertStringContainsString('Published /summer-camp (scheduled for 2026-11-05 09:30).', $output);
        $this->assertStringContainsString('1 scheduled page published.', $output);
        $this->camp->refresh();
        $this->assertSame([Page::PUBLISHED, '2026-11-05 09:30:00'], [$this->camp->status, $this->camp->published_at->format('Y-m-d H:i:s')], 'the scheduled time stays the publication date');
        $revision = PageRevision::latest('id')->first();
        $this->assertSame(['Before scheduled publication', null, Page::SCHEDULED], [$revision->revision_label, $revision->user_id, $revision->snapshot['page']['status']]);
        $this->assertSame(self::SHOWN, $this->publicView());

        // Running again, now or later, changes nothing.
        $updatedAt = DB::table('pages')->where('id', $this->camp->id)->value('updated_at');
        $this->travel(5)->minutes();
        $this->assertStringContainsString('No scheduled pages are due.', $this->runScheduler());
        $this->assertStringContainsString('No scheduled pages are due.', $this->runScheduler());
        $this->assertSame($revisions + 1, PageRevision::count());
        $this->assertSame($updatedAt, DB::table('pages')->where('id', $this->camp->id)->value('updated_at'));
    }
    public function test_the_scheduler_handles_many_due_pages_and_skips_drafts_and_future_pages(): void
    {
        foreach (range(1, 120) as $i) $this->page("event-{$i}", "Event {$i}", 'scheduled', Carbon::parse('2026-11-01 08:00', self::TZ)->addSeconds($i));
        $later = $this->page('later', 'Later', 'scheduled', Carbon::parse('2026-12-01 08:00', self::TZ));
        $draft = $this->page('old-draft', 'Old draft', 'draft', Carbon::parse('2026-10-01 08:00', self::TZ));
        $this->assertStringContainsString('120 scheduled pages published.', $this->runScheduler());
        $this->assertSame(0, Page::where('status', Page::SCHEDULED)->where('published_at', '<=', now())->count());
        $this->assertSame([Page::SCHEDULED, Page::DRAFT], [$later->fresh()->status, $draft->fresh()->status]);
    }
    public function test_archived_pages_are_never_published_or_shown(): void
    {
        $this->schedule();
        $this->delete(route('admin.pages.destroy', $this->camp))->assertRedirect();
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00', self::TZ));
        $this->assertStringContainsString('No scheduled pages are due.', $this->runScheduler());
        $archived = Page::withTrashed()->find($this->camp->id);
        $this->assertTrue($archived->trashed());
        $this->assertSame(Page::SCHEDULED, $archived->status);
        $this->assertSame('archived', $archived->publicationState());
        $this->assertSame(self::HIDDEN, $this->publicView());
        // Restoring brings it back as a draft, which the scheduler leaves alone.
        $this->actingAs($this->admin)->post(route('admin.pages.restore', $this->camp));
        $this->runScheduler();
        $this->assertSame(Page::DRAFT, $this->camp->fresh()->status);
        $this->assertSame(self::HIDDEN, $this->publicView());
    }
    public function test_home_and_contact_stay_live(): void
    {
        foreach (Page::whereIn('slug', Page::CORE_SLUGS)->get() as $core) {
            $this->settings($core, ['status' => 'scheduled', 'published_at' => '2026-11-05T09:30'])->assertSessionHasErrors(['status' => "The {$core->title} page must stay published, so it cannot be changed to scheduled."]);
            $this->settings($core, ['status' => 'draft'])->assertSessionHasErrors(['status' => "The {$core->title} page must stay published, so it cannot be changed to a draft."]);
            $this->from(route('admin.pages.edit', $core))->post(route('admin.pages.unpublish', $core))->assertSessionHasErrors(['status' => "The {$core->title} page must stay published, so it cannot be unpublished."]);
            $this->delete(route('admin.pages.destroy', $core))->assertStatus(422);
            $this->settings($core, ['status' => 'published'])->assertSessionHasNoErrors();
            $this->assertTrue($core->fresh()->isLive());
            $this->get(route('admin.pages.edit', $core))->assertSee('data-core-page', false)->assertDontSee('>Unpublish</button>', false);
        }
        // A revision recorded while Home was a draft cannot take it offline again.
        $home = Page::where('slug', 'home')->first();
        $revision = PageRevision::create(['page_id' => $home->id, 'revision_label' => 'Old draft', 'snapshot' => ['page' => ['status' => 'draft']]]);
        $this->from(route('admin.pages.edit', $home))->post(route('admin.pages.revisions.restore', [$home, $revision]))->assertSessionHasErrors('revision');
        $this->assertTrue($home->fresh()->isLive());
    }
    public function test_publish_now_and_cancel_schedule_record_revisions(): void
    {
        $this->schedule();
        $this->post(route('admin.pages.unpublish', $this->camp))->assertSessionHas('success', 'Schedule cancelled. The page is a draft again.');
        $this->assertSame(Page::DRAFT, $this->camp->fresh()->status);
        $this->assertSame('Before cancelling the schedule', PageRevision::latest('id')->value('revision_label'));
        $this->schedule();
        $this->post(route('admin.pages.publish', $this->camp))->assertSessionHas('success', 'Page published.');
        $this->camp->refresh();
        $this->assertSame([Page::PUBLISHED, '2026-11-01 09:00:00'], [$this->camp->status, $this->camp->published_at->format('Y-m-d H:i:s')]);
        $this->assertSame('Before publishing', PageRevision::latest('id')->value('revision_label'));
        $this->assertSame(self::SHOWN, $this->publicView());
    }
    public function test_schedules_are_kept_in_revisions_and_restore_in_local_time(): void
    {
        $this->schedule();
        $this->settings($this->camp->fresh(), ['status' => 'draft']);
        $revision = PageRevision::where('page_id', $this->camp->id)->latest('id')->first();
        $this->assertSame(['scheduled', '2026-11-05 09:30:00'], [$revision->snapshot['page']['status'], $revision->snapshot['page']['published_at']]);
        $this->post(route('admin.pages.revisions.restore', [$this->camp, $revision]))->assertSessionHasNoErrors();
        $this->assertSame([Page::SCHEDULED, '2026-11-05 09:30:00'], [$this->camp->fresh()->status, DB::table('pages')->where('id', $this->camp->id)->value('published_at')]);
    }
    public function test_a_scheduled_page_keeps_its_hero_like_a_published_one(): void
    {
        $this->schedule();
        $hero = $this->camp->sections()->first();
        $this->delete(route('admin.pages.sections.destroy', [$this->camp, $hero]))->assertSessionHasErrors('publish');
        $this->assertNotNull($hero->fresh());
    }
    public function test_scheduling_and_publication_clear_the_cached_menus(): void
    {
        Cache::put(PublicSiteData::CACHE_KEY, ['stale' => true], 600);
        $this->schedule();
        $this->assertFalse(Cache::has(PublicSiteData::CACHE_KEY), 'saving the schedule');
        $this->asVisitor();
        $this->get('/contact')->assertOk();
        $this->assertTrue(Cache::has(PublicSiteData::CACHE_KEY));
        $this->travelTo(Carbon::parse('2026-11-05 09:31:00', self::TZ));
        $this->runScheduler();
        $this->assertFalse(Cache::has(PublicSiteData::CACHE_KEY), 'the scheduled publication');
    }
    public function test_times_mean_asia_kolkata_whatever_clock_they_are_compared_with(): void
    {
        $this->assertSame(self::TZ, config('app.timezone'));
        $this->schedule('2026-11-05T09:30');
        // 09:30 in Kolkata is 04:00 UTC: the same instant, so the page is visible from exactly then.
        $this->travelTo(Carbon::parse('2026-11-05 03:59:59', 'UTC'));
        $this->assertSame(404, $this->publicView()['page']);
        $this->travelTo(Carbon::parse('2026-11-05 04:00:00', 'UTC'));
        $this->assertSame(200, $this->publicView()['page']);
        $this->assertStringContainsString('1 scheduled page published.', $this->runScheduler());
        $this->assertSame('2026-11-05 09:30:00', DB::table('pages')->where('id', $this->camp->id)->value('published_at'));
    }
    public function test_the_menu_editor_explains_a_scheduled_link_and_preview_still_shows_the_page(): void
    {
        $this->schedule();
        $this->get(route('admin.menus.index'))->assertSee('the page at /summer-camp is scheduled for 05 Nov 2026 09:30. It will appear then.');
        $this->get(route('admin.pages.preview', $this->camp))->assertOk()->assertSee('Summer Camp');
    }
    public function test_the_scheduler_is_registered_to_run_every_minute(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'kelika:publish-scheduled'));
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}

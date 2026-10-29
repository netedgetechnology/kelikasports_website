<?php
namespace Tests\Feature;
use App\Models\ContentItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/**
 * The Events page design (design-reference/Kelika Sports - Events Page.png): opt-in section layouts that keep the event
 * visibility safeguards (demo records only in CMS preview and development demo mode) and leave the default layouts as
 * they were.
 */
class EventsLayoutsTest extends TestCase
{
    use RefreshDatabase;
    private Page $page;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['kelika.demo_content.public' => false, 'kelika.demo_content.hosts' => []]);
        $this->page = Page::create(['title' => 'Events', 'slug' => 'events', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
    }
    private function section(string $type, array $data): PageSection
    {
        return PageSection::create(['page_id' => $this->page->id, 'type' => $type, 'sort_order' => PageSection::count() + 1, 'is_visible' => true, 'data' => $data]);
    }
    private function event(string $title, string $status, array $data, array $attributes = []): ContentItem
    {
        return ContentItem::create($attributes + ['type' => 'event', 'title' => $title, 'status' => $status, 'sort_order' => ContentItem::count() + 1, 'data' => $data]);
    }
    private function demoEvents(): void
    {
        $this->event('Kelika Winter Championship', 'draft', ['demo' => true, 'demo_note' => 'Internal: do not publish until verified.', 'date_label' => '18 Dec', 'eyebrow' => 'Badminton', 'location' => 'Indoor Arena, Kelika', 'image' => '/images/home/event-winter-championship.jpg', 'url' => '/events']);
    }
    private function html(): string
    {
        return $this->get('/events')->assertOk()->getContent();
    }
    public function test_event_cards_show_published_events_with_their_date_category_and_location(): void
    {
        $this->section('event_list', ['layout' => 'cards', 'eyebrow' => 'UPCOMING EVENTS', 'title' => 'Join the *Action*', 'subtitle' => 'Confirmed details are published here.']);
        $this->event('Club Open', 'published', ['eyebrow' => 'Badminton', 'location' => 'Kelika Sports Complex', 'image' => '/images/home/event-winter-championship.jpg', 'image_alt' => 'Shuttlecock', 'registration_url' => 'https://register.example.test/open'], ['starts_at' => now()->addDays(10)->setDate(2030, 3, 7)]);
        $this->event('Coaching Day', 'published', ['date_label' => '12 Jan', 'url' => '/events']);
        $this->event('Unpublished Draft', 'draft', ['date_label' => '1 Jan']);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-events-grid" id="upcoming-events">', $html);
        $this->assertStringContainsString('<span class="kl-heading__highlight">Action</span>', $html);
        $this->assertStringContainsString('<p class="kl-split-head__text">Confirmed details are published here.</p>', $html);
        $this->assertMatchesRegularExpression('#<time class="kl-event__date" datetime="2030-03-07">\s*<strong>07</strong>MAR#', $html);
        $this->assertStringContainsString('<p class="kl-events-grid__category">Badminton</p>', $html);
        $this->assertStringContainsString('<h3 class="kl-events-grid__title">Club Open</h3>', $html);
        $this->assertStringContainsString('Kelika Sports Complex', $html);
        $this->assertStringContainsString('alt="Shuttlecock"', $html);
        $this->assertStringContainsString('href="https://register.example.test/open"  target="_blank" rel="noopener noreferrer"  aria-label="Details for Club Open"', $html);
        // A short date label drives the badge; a details link back to this page is not shown; drafts never appear.
        $this->assertStringContainsString('<span class="kl-event__date"><strong>12</strong>JAN</span>', $html);
        $this->assertStringNotContainsString('aria-label="Details for Coaching Day"', $html);
        $this->assertStringNotContainsString('Unpublished Draft', $html);
        $this->assertSame(2, substr_count($html, '<article class="kl-event">'));
    }
    public function test_demo_events_stay_hidden_from_visitors_who_see_neutral_cards_instead(): void
    {
        $this->section('event_list', ['layout' => 'cards', 'title' => 'Join the Action']);
        $this->demoEvents();
        $html = $this->html();
        $this->assertStringNotContainsString('Kelika Winter Championship', $html);
        $this->assertStringNotContainsString('do not publish', $html);
        $this->assertSame(3, substr_count($html, '<article class="kl-event kl-event--empty">'));
        $this->assertStringContainsString('Event announcement coming soon', $html);
        $this->assertStringNotContainsString('kl-circle-link', $html);
        $this->assertStringNotContainsString('No upcoming events are published', $html);
    }
    public function test_demo_events_show_in_development_demo_mode_and_in_cms_preview_with_a_note(): void
    {
        $this->section('event_list', ['layout' => 'cards', 'title' => 'Join the Action']);
        $this->demoEvents();
        config(['kelika.demo_content.public' => true, 'kelika.demo_content.hosts' => [parse_url(config('app.url'), PHP_URL_HOST), 'localhost']]);
        $html = $this->html();
        $this->assertStringContainsString('Kelika Winter Championship', $html);
        $this->assertStringNotContainsString('Demo events — unverified', $html);
        $this->assertStringNotContainsString('do not publish', $html);

        config(['kelika.demo_content.public' => false, 'kelika.demo_content.hosts' => []]);
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $preview = $this->actingAs($editor)->get(route('admin.pages.preview', $this->page))->assertOk()->getContent();
        $this->assertStringContainsString('Kelika Winter Championship', $preview);
        $this->assertStringContainsString('Demo events — unverified', $preview);
    }
    public function test_the_why_participate_strip_shows_the_heading_and_icon_features(): void
    {
        $this->section('feature_list', ['layout' => 'strip', 'title' => 'Why Participate?', 'items' => [
            ['title' => 'Improve Skills', 'description' => 'Gain match experience.', 'icon' => 'target'],
            ['title' => 'Pathway to Growth', 'description' => 'Explore development opportunities.', 'icon' => 'chart', 'color' => '#123456'],
        ]]);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-why" id="why-participate">', $html);
        $this->assertStringContainsString('class="kl-heading kl-heading--bold kl-why__title"', $html);
        $this->assertStringContainsString('<li class="kl-why__item" style="--kl-item: #d4652e">', $html);
        $this->assertStringContainsString('<li class="kl-why__item" style="--kl-item: #123456">', $html);
        $this->assertStringContainsString(\App\Support\Icon::svg('target', 'kl-icon kl-icon--xl'), $html);
        $this->assertStringContainsString('<p class="kl-why__item-text">Explore development opportunities.</p>', $html);
    }
    public function test_the_event_cta_links_to_the_events_and_the_contact_page(): void
    {
        $this->section('cta', ['layout' => 'program', 'tone' => 'light', 'title' => '*Play. Compete.*|Connect.', 'heading_accent' => 'terracotta',
            'button_label' => 'View Upcoming Events', 'button_url' => '#upcoming-events', 'secondary_button_label' => 'Contact Us', 'secondary_button_url' => '/contact']);
        $html = $this->html();
        $this->assertStringContainsString('<span class="kl-heading__highlight">Play. Compete.</span>', $html);
        $this->assertStringContainsString('<span class="d-block kl-accent-terracotta">Connect. </span>', $html);
        $this->assertMatchesRegularExpression('#href="\#upcoming-events">View Upcoming Events#', $html);
        $this->assertMatchesRegularExpression('#href="/contact"\s*>Contact Us#', $html);
        $this->assertStringNotContainsString('Register', $html);
    }
    public function test_the_events_hero_title_gets_the_long_title_size_and_a_five_item_strip(): void
    {
        // "Sports Community." is 17 characters, so the existing rule (longest line over 16) gives the smaller headline
        // and the title keeps the reference's four lines.
        $this->section('hero', ['layout' => 'cinematic', 'title' => 'Tournaments,|Camps & Events|*For a Stronger*|*Sports Community.*', 'image' => '/images/home/hero-player.jpg',
            'items' => array_map(fn ($t) => ['title' => $t, 'icon' => 'trophy'], ['Tournaments', 'Training|Camps', 'Workshops', 'Community|Events', 'Upcoming|Events'])]);
        $html = $this->html();
        $this->assertStringContainsString('kl-hero--long-title', $html);
        $this->assertStringContainsString('kl-hero__features kl-hero__features--five', $html);
        $this->assertStringContainsString('<span class="kl-hero__highlight kl-accent-gold">Sports Community.</span>', $html);
    }
    public function test_event_lists_without_the_cards_layout_keep_their_templates(): void
    {
        $this->section('event_list', ['title' => 'Upcoming']);
        $this->event('Club Open', 'published', ['date_label' => '7 Mar']);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section" id="upcoming-events">', $html);
        $this->assertStringContainsString('soft-card event-card', $html);
        $this->assertStringNotContainsString('kl-events-grid', $html);
    }
    public function test_the_showcase_features_the_marked_event_beside_the_upcoming_list(): void
    {
        $this->section('event_list', ['layout' => 'showcase', 'eyebrow' => 'UPCOMING EVENTS', 'title' => 'Upcoming Events', 'button_label' => 'View All', 'button_url' => '/calendar']);
        $this->event('Coaching Day', 'published', ['date_label' => '12 Oct', 'eyebrow' => 'Singles & Doubles', 'location' => 'Kelika Sports Complex', 'url' => '/events']);
        $this->event('Kelika Open', 'published', ['featured' => true, 'summary' => 'Compete. Improve.', 'location' => 'Kelika Sports Complex', 'formats' => 'Singles / Doubles', 'categories' => 'U9, U13, Open',
            'image' => '/images/home/stats-shuttlecock.jpg', 'image_alt' => 'Shuttlecock', 'url' => '/academy'], ['starts_at' => '2030-11-15 09:00:00', 'ends_at' => '2030-11-17 18:00:00']);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-event-showcase" id="upcoming-events">', $html);
        $this->assertStringContainsString('<h2 class="visually-hidden">Upcoming Events</h2>', $html);
        $this->assertMatchesRegularExpression('#<article class="kl-feature-event">.*?<h3 class="kl-feature-event__title">Kelika Open</h3>#s', $html);
        $this->assertStringContainsString('<p class="kl-feature-event__text">Compete. Improve.</p>', $html);
        $this->assertStringContainsString('<span>15 – 17 Nov 2030</span>', $html);
        foreach (['Singles / Doubles', 'U9, U13, Open'] as $fact) $this->assertStringContainsString('<span>'.$fact.'</span>', $html);
        $this->assertMatchesRegularExpression('#href="/academy"\s*>\s*View Details#', $html);
        // The list includes every upcoming event (featured too), with date badges; only rows with another page link.
        $this->assertSame(2, substr_count($html, '<li class="kl-event-row">'));
        $this->assertStringContainsString('<time class="kl-event-row__date" datetime="2030-11-15"><strong>15</strong>NOV</time>', $html);
        $this->assertStringContainsString('<span class="kl-event-row__date"><strong>12</strong>OCT</span>', $html);
        $this->assertStringContainsString('<h3 class="kl-event-row__title">Coaching Day</h3>', $html);
        $this->assertSame(1, substr_count($html, 'class="kl-event-row__arrow"'));
        $this->assertMatchesRegularExpression('#class="kl-event-list__all" href="/calendar">View All#', $html);
        $this->assertStringNotContainsString('Register', $html);
    }
    public function test_the_showcase_features_the_first_event_without_a_marked_one_and_hides_self_links(): void
    {
        $this->section('event_list', ['layout' => 'showcase', 'button_label' => 'View All', 'button_url' => '/events']);
        $this->event('Coaching Day', 'published', ['date_label' => '12 Oct', 'url' => '/events']);
        $html = $this->html();
        $this->assertMatchesRegularExpression('#kl-feature-event__title">Coaching Day</h3>#', $html);
        $this->assertStringContainsString('<span>12 Oct</span>', $html);
        $this->assertStringContainsString('kl-feature-event--no-media', $html);
        $this->assertStringNotContainsString('View Details', $html);
        $this->assertStringNotContainsString('kl-event-list__all', $html);
        $this->assertStringNotContainsString('kl-event-row__arrow', $html);
    }
    public function test_the_showcase_keeps_demo_events_from_visitors_and_shows_neutral_panels(): void
    {
        $this->section('event_list', ['layout' => 'showcase', 'image' => '/images/home/hero-player.jpg', 'image_alt' => 'Indoor hall']);
        $this->demoEvents();
        $html = $this->html();
        $this->assertStringNotContainsString('Kelika Winter Championship', $html);
        $this->assertStringNotContainsString('do not publish', $html);
        $this->assertStringContainsString('<article class="kl-feature-event kl-feature-event--empty">', $html);
        $this->assertStringContainsString('Next event to be announced', $html);
        $this->assertStringContainsString('alt="Indoor hall"', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('page.show', ['slug' => 'contact']), '#').'">Contact Us#', $html);
        $this->assertStringContainsString('Upcoming events will be listed here', $html);
        $this->assertStringNotContainsString('kl-event-row', $html);
        $this->assertStringNotContainsString('kl-feature-event__facts', $html);

        config(['kelika.demo_content.public' => true, 'kelika.demo_content.hosts' => [parse_url(config('app.url'), PHP_URL_HOST), 'localhost']]);
        $demo = $this->html();
        $this->assertMatchesRegularExpression('#kl-feature-event__title">Kelika Winter Championship</h3>#', $demo);
        $this->assertStringContainsString('<span>Badminton</span>', $demo);
        $this->assertStringNotContainsString('do not publish', $demo);
    }
    public function test_gallery_demo_images_reach_only_preview_and_demo_mode_and_visitors_see_neutral_tiles(): void
    {
        $gallery = $this->section('gallery', ['layout' => 'strip', 'eyebrow' => 'EVENT GALLERY', 'title' => 'Moments that *Inspire*', 'subtitle' => 'A look at our community.',
            'button_label' => 'View Gallery', 'button_url' => '', 'demo_content' => true,
            'items' => array_map(fn ($n) => ['image' => '/images/home/stats-shuttlecock.jpg', 'image_alt' => "Stock image $n"], range(1, 6))]);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-gallery-strip" id="event-gallery">', $html);
        $this->assertStringContainsString('<span class="kl-heading__highlight">Inspire</span>', $html);
        $this->assertStringContainsString('<p class="kl-split-head__text">A look at our community.</p>', $html);
        $this->assertStringNotContainsString('Stock image', $html);
        $this->assertSame(5, substr_count($html, 'kl-gallery-strip__tile--empty'));
        $this->assertStringContainsString('Photos from KELIKA events will be shared here.', $html);
        $this->assertStringNotContainsString('View Gallery', $html, 'no button without a destination');

        config(['kelika.demo_content.public' => true, 'kelika.demo_content.hosts' => [parse_url(config('app.url'), PHP_URL_HOST), 'localhost']]);
        $demo = $this->html();
        $this->assertSame(5, substr_count($demo, 'alt="Stock image'), 'one row of five');
        $this->assertStringNotContainsString('kl-gallery-strip__tile--empty', $demo);
        $this->assertStringNotContainsString('Demo images', $demo);

        config(['kelika.demo_content.public' => false, 'kelika.demo_content.hosts' => []]);
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $preview = $this->actingAs($editor)->get(route('admin.pages.preview', $this->page))->assertOk()->getContent();
        $this->assertStringContainsString('alt="Stock image 1"', $preview);
        $this->assertStringContainsString('Demo images — not approved KELIKA photographs', $preview);

        // Approved images (demo flag off) and a real gallery page: visitors see the photos and the button.
        auth()->logout();
        $gallery->update(['data' => ['demo_content' => false, 'button_url' => '/gallery'] + $gallery->data]);
        $approved = $this->html();
        $this->assertStringContainsString('alt="Stock image 1"', $approved);
        $this->assertMatchesRegularExpression('#href="/gallery">View Gallery#', $approved);
    }
    public function test_a_gallery_without_the_strip_layout_stays_hidden_until_it_has_images(): void
    {
        $this->section('gallery', ['eyebrow' => 'EVENT GALLERY', 'title' => 'MOMENTS THAT|INSPIRE']);
        $html = $this->html();
        $this->assertStringNotContainsString('MOMENTS THAT', $html);
        $this->assertStringNotContainsString('kl-gallery-strip', $html);
    }
    public function test_the_complete_events_page_renders_its_sections_in_reference_order(): void
    {
        $this->section('hero', ['layout' => 'cinematic', 'eyebrow' => 'EVENTS', 'title' => 'Tournaments,|Camps & Events|*For a Stronger*|*Sports Community.*', 'image' => '/images/home/hero-player.jpg',
            'button_label' => 'Explore Events', 'button_url' => '#upcoming-events', 'secondary_button_label' => 'Contact Us', 'secondary_button_url' => '/contact',
            'items' => array_map(fn ($t) => ['title' => $t, 'icon' => 'trophy'], ['Tournaments', 'Training|Camps', 'Workshops', 'Community|Events', 'Upcoming|Events'])]);
        $this->section('event_list', ['layout' => 'showcase', 'eyebrow' => 'UPCOMING EVENTS', 'title' => 'Upcoming Events']);
        $this->section('feature_list', ['layout' => 'strip', 'title' => 'Why Participate?', 'items' => [['title' => 'Improve Skills', 'icon' => 'target']]]);
        $this->section('gallery', ['layout' => 'strip', 'eyebrow' => 'EVENT GALLERY', 'title' => 'Moments that *Inspire*']);
        $this->section('cta', ['layout' => 'program', 'tone' => 'light', 'eyebrow' => 'BE PART OF OUR NEXT EVENT', 'title' => '*Play. Compete.*|Connect.',
            'button_label' => 'View Upcoming Events', 'button_url' => '#upcoming-events', 'secondary_button_label' => 'Contact Us', 'secondary_button_url' => '/contact']);
        $html = $this->html();
        $this->assertMatchesRegularExpression('#kl-hero--cinematic.*?kl-hero__features--five.*?kl-event-showcase.*?kl-feature-event.*?kl-event-list.*?kl-why.*?kl-gallery-strip.*?kl-cta--program.*?site-footer#s', $html);
        foreach (['Explore Events', 'View Upcoming Events'] as $label) $this->assertMatchesRegularExpression('#href="\#upcoming-events"[^>]*>\s*'.$label.'#', $html);
        $this->assertSame(1, substr_count($html, 'id="upcoming-events"'));
        $this->assertSame(2, preg_match_all('#href="/contact"\s*>\s*Contact Us#', $html));
    }
    public function test_editors_can_mark_gallery_images_as_demo_content_and_set_event_section_photos(): void
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $gallery = $this->section('gallery', ['title' => 'Gallery']);
        $this->actingAs($editor)->get(route('admin.pages.sections.edit', [$this->page, $gallery]))->assertOk()
            ->assertSee('Heading row above one row of five photos (Events design)')->assertSee('Demo content — unapproved images')->assertSee('id="button_label"', false);
        $url = route('admin.pages.sections.update', [$this->page, $gallery]);
        $this->actingAs($editor)->put($url, ['title' => 'Moments that *Inspire*', 'is_visible' => '1', 'layout' => 'strip', 'demo_content' => '1', 'button_label' => 'View Gallery',
            'items_json' => json_encode([['image' => '/images/home/stats-shuttlecock.jpg', 'image_alt' => 'Shuttle', 'image_position' => 'top', 'caption' => '']])])->assertSessionHasNoErrors();
        $data = $gallery->fresh()->data;
        $this->assertSame(['strip', true, 'View Gallery', 'top'], [$data['layout'], $data['demo_content'], $data['button_label'], $data['items'][0]['image_position']]);
        $this->actingAs($editor)->put($url, ['title' => 'Moments that *Inspire*', 'is_visible' => '1', 'layout' => 'strip'])->assertSessionHasNoErrors();
        $this->assertFalse($gallery->fresh()->data['demo_content'], 'unticking approves the images');

        $list = $this->section('event_list', ['title' => 'Events']);
        $this->actingAs($editor)->get(route('admin.pages.sections.edit', [$this->page, $list]))->assertOk()->assertSee('Featured event panel beside an upcoming events list (Events design)')->assertSee('name="image"', false);
        $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $list]), ['title' => 'Upcoming Events', 'is_visible' => '1', 'layout' => 'showcase', 'image' => '/images/home/hero-player.jpg', 'image_alt' => 'Hall'])->assertSessionHasNoErrors();
        $this->assertSame(['showcase', '/images/home/hero-player.jpg'], [$list->fresh()->data['layout'], $list->fresh()->data['image']]);
    }
    public function test_editors_can_choose_the_events_layouts(): void
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $list = $this->section('event_list', ['title' => 'Events']);
        $this->actingAs($editor)->get(route('admin.pages.sections.edit', [$this->page, $list]))->assertOk()->assertSee('Heading row above three-column event cards (Events design)');
        $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $list]), ['title' => 'Join the *Action*', 'subtitle' => 'Published here.', 'layout' => 'cards'])->assertSessionHasNoErrors();
        $this->assertSame(['cards', 'Join the *Action*', 'Published here.'], [$list->fresh()->data['layout'], $list->fresh()->data['title'], $list->fresh()->data['subtitle']]);
        $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $list]), ['layout' => 'strip'])->assertSessionHasErrors('layout');

        $why = $this->section('feature_list', ['title' => 'Why']);
        $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $why]), [
            'title' => 'Why Participate?', 'is_visible' => '1', 'layout' => 'strip', 'items_json' => json_encode([['title' => 'Improve Skills', 'description' => 'Gain match experience.', 'icon' => 'target', 'color' => '#d4652e']]),
        ])->assertSessionHasNoErrors();
        $this->assertSame(['strip', 'target'], [$why->fresh()->data['layout'], $why->fresh()->data['items'][0]['icon']]);
        $this->assertStringContainsString('Improve Skills', $this->html());
    }
}

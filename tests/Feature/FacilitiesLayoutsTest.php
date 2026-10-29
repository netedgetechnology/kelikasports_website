<?php
namespace Tests\Feature;
use App\Models\ContentItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/**
 * The Facilities page design (design-reference/Kelika Sports - Facilities.png): opt-in section layouts that leave the
 * default layouts, and the Facility records shared with the Home and Academy pages, unchanged.
 */
class FacilitiesLayoutsTest extends TestCase
{
    use RefreshDatabase;
    private Page $page;
    private string $booking;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->page = Page::create(['title' => 'Facilities', 'slug' => 'facilities', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
        ContentItem::create(['type' => 'facility', 'title' => 'Player Lounge', 'status' => 'published', 'sort_order' => 1, 'data' => ['description' => 'Relax between sessions.']]);
        ContentItem::create(['type' => 'facility', 'title' => 'Changing Rooms', 'status' => 'published', 'sort_order' => 2, 'data' => ['color' => '#123456']]);
        ContentItem::create(['type' => 'facility', 'title' => 'Unconfirmed Sauna', 'status' => 'draft', 'sort_order' => 3, 'data' => []]);
        $this->booking = e(route('page.show', ['slug' => 'contact', 'topic' => 'Court Booking']).'#contact-form');
    }
    private function section(string $type, array $data): PageSection
    {
        return PageSection::create(['page_id' => $this->page->id, 'type' => $type, 'sort_order' => PageSection::count() + 1, 'is_visible' => true, 'data' => $data]);
    }
    private function html(): string
    {
        return $this->get('/facilities')->assertOk()->getContent();
    }
    public function test_the_courts_layout_shows_the_copy_photo_and_image_cards(): void
    {
        $this->section('split', ['layout' => 'courts', 'eyebrow' => 'MAIN ATTRACTION', 'title' => 'Professional|Badminton Courts', 'heading_accent' => 'none',
            'body' => 'Courts for play and training.', 'button_label' => 'Book a Court', 'image' => '/images/home/hero-player.jpg', 'image_alt' => 'Indoor hall',
            'items' => [
                ['title' => 'Court Flooring', 'description' => 'Court surfaces.', 'image' => '/images/home/stats-shuttlecock.jpg', 'image_alt' => 'Court lines'],
                ['title' => 'Court Lighting', 'description' => 'Indoor lighting.', 'url' => '/contact'],
                ['title' => ''],
            ]]);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-courts" id="main-attraction">', $html);
        $this->assertStringContainsString('<span class="d-block ">Badminton Courts </span>', $html);
        $this->assertStringContainsString('<p class="kl-courts__text">Courts for play and training.</p>', $html);
        // Without a URL, Book a Court opens the contact form on the Court Booking topic (no booking URL is configured).
        $this->assertMatchesRegularExpression('#href="'.preg_quote($this->booking, '#').'">\s*Book a Court#', $html);
        $this->assertStringContainsString('alt="Indoor hall"', $html);
        $this->assertSame(2, substr_count($html, '<li class="kl-court-card">'));
        $this->assertStringContainsString('alt="Court lines"', $html);
        // A card without an image shows its icon; only a card with a URL is a link.
        $this->assertStringContainsString('<span class="kl-court-card__icon">', $html);
        $this->assertStringContainsString('<h3 class="kl-court-card__title">Court Flooring</h3>', $html);
        $this->assertStringContainsString('<a class="stretched-link" href="/contact">Court Lighting</a>', $html);
    }
    public function test_the_courts_layout_uses_a_configured_booking_url(): void
    {
        \App\Models\Setting::query()->updateOrCreate(['key' => 'booking_url'], ['value' => 'https://booking.example.test/courts']);
        cache()->flush();
        $this->section('split', ['layout' => 'courts', 'title' => 'Courts', 'button_label' => 'Book a Court']);
        $html = $this->html();
        $this->assertStringContainsString('kl-courts--no-media', $html);
        $this->assertMatchesRegularExpression('#href="https://booking\.example\.test/courts">\s*Book a Court#', $html);
    }
    public function test_the_amenities_layout_lists_the_published_facility_records_beside_the_photo(): void
    {
        $this->section('facilities_showcase', ['layout' => 'amenities', 'eyebrow' => 'BEYOND THE COURTS', 'title' => 'Modern Amenities|for a Complete Experience', 'heading_accent' => 'terracotta',
            'subtitle' => 'A range of amenities.', 'image' => '/images/home/hero-player.jpg', 'image_alt' => 'Sports hall']);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-amenities-band" id="facility-list">', $html);
        $this->assertStringContainsString('<span class="d-block kl-accent-terracotta">for a Complete Experience </span>', $html);
        $this->assertStringContainsString('<p class="kl-amenities-band__text">A range of amenities.</p>', $html);
        $this->assertStringContainsString('alt="Sports hall"', $html);
        $this->assertMatchesRegularExpression('#Player Lounge</h3>\s*<p class="kl-amenity__text">Relax between sessions\.</p>#', $html);
        $this->assertStringContainsString('style="--kl-item: #123456"', $html);
        // Icons follow the facility titles (lounge → sofa, changing → hanger); drafts are never listed.
        $this->assertStringContainsString(\App\Support\Icon::svg('sofa', 'kl-icon kl-icon--lg'), $html);
        $this->assertStringContainsString(\App\Support\Icon::svg('hanger', 'kl-icon kl-icon--lg'), $html);
        $this->assertStringNotContainsString('Unconfirmed Sauna', $html);
    }
    public function test_the_amenities_layout_without_a_photo_spans_the_width(): void
    {
        $this->section('facilities_showcase', ['layout' => 'amenities', 'title' => 'Amenities']);
        $html = $this->html();
        $this->assertStringContainsString('kl-amenities-band--no-media', $html);
        $this->assertStringNotContainsString('kl-amenities-band__media', $html);
    }
    public function test_a_facilities_showcase_without_a_layout_keeps_its_card_grid(): void
    {
        $this->section('facilities_showcase', ['title' => 'Our Facilities', 'image' => '/images/home/hero-player.jpg']);
        $html = $this->html();
        $this->assertStringContainsString('<section class="section-pad" id="facility-list">', $html);
        $this->assertStringContainsString('<h3 class="h5 fw-bold">Player Lounge</h3>', $html);
        $this->assertStringNotContainsString('kl-amenities-band', $html);
    }
    public function test_the_spaces_layout_shows_icon_tiles_without_arrows(): void
    {
        $this->section('card_grid', ['layout' => 'spaces', 'eyebrow' => 'ADDITIONAL AREAS', 'title' => 'Spaces to Support|Every Need', 'subtitle' => 'Contact our team to confirm what is available.', 'items' => [
            ['title' => 'Training Zone', 'description' => 'Availability and details to be confirmed.', 'icon' => 'dumbbell', 'color' => '#1f8a52'],
            ['title' => 'Viewing Area', 'description' => 'Availability and details to be confirmed.', 'image' => '/images/home/stats-shuttlecock.jpg'],
        ]]);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-pathway kl-pathway--spaces" id="additional-areas">', $html);
        $this->assertStringContainsString('<li class="kl-stage kl-stage--icon">', $html);
        $this->assertStringContainsString('<span class="kl-stage__icon" style="--kl-item: #1f8a52">'.\App\Support\Icon::svg('dumbbell', 'kl-icon kl-icon--xl'), $html);
        $this->assertSame(1, substr_count($html, 'kl-stage--icon'));
        $this->assertStringNotContainsString('kl-stage__arrow', $html);
    }
    public function test_the_programs_pathway_still_has_arrows_and_no_icon_tiles(): void
    {
        $this->section('card_grid', ['layout' => 'pathway', 'title' => 'Pathway', 'items' => [['title' => 'Foundation', 'icon' => 'users'], ['title' => 'Competitive']]]);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-pathway" id="training-structure">', $html);
        $this->assertSame(1, substr_count($html, 'class="kl-stage__arrow"'));
        $this->assertStringNotContainsString('kl-stage__icon', $html);
        $this->assertSame(2, substr_count($html, '<li class="kl-stage">'));
    }
    public function test_a_six_item_hero_strip_gets_its_own_row_modifier_and_book_a_court_keeps_its_fallback(): void
    {
        $this->section('hero', ['layout' => 'cinematic', 'title' => 'World-class|Facilities for|*Every Athlete*', 'heading_accent' => 'terracotta', 'image' => '/images/home/hero-player.jpg',
            'button_label' => 'Explore Facilities', 'button_url' => '#main-attraction', 'secondary_button_label' => 'Book a Court', 'secondary_button_url' => '',
            'items' => array_map(fn ($t) => ['title' => $t, 'icon' => 'pin'], ['Professional|Courts', 'Modern|Amenities', 'Safe & Clean|Environment', 'Coaching|Support', 'Events &|Tournaments', 'Accessible|Location'])]);
        $html = $this->html();
        $this->assertStringContainsString('class="kl-hero__features kl-hero__features--six"', $html);
        $this->assertStringNotContainsString('kl-hero__features--five', $html);
        $this->assertStringContainsString('<span class="kl-hero__highlight kl-accent-terracotta">Every Athlete</span>', $html);
        $this->assertMatchesRegularExpression('#href="\#main-attraction">\s*Explore Facilities#', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote($this->booking, '#').'"\s*>\s*Book a Court#', $html);
    }
    public function test_the_shared_cta_layout_highlights_the_first_heading_line(): void
    {
        $this->section('cta', ['layout' => 'program', 'tone' => 'light', 'title' => '*Book a Court or*|Visit Our Facilities', 'heading_accent' => 'terracotta',
            'button_label' => 'Book a Court', 'button_url' => '/contact?topic=Court%20Booking#contact-form', 'secondary_button_label' => 'Contact Us', 'secondary_button_url' => '/contact', 'script_text' => 'Play|Train|Grow|Belong']);
        $html = $this->html();
        $this->assertStringContainsString('kl-cta--program', $html);
        $this->assertStringContainsString('<span class="kl-heading__highlight">Book a Court or</span>', $html);
        $this->assertStringContainsString('<span class="d-block kl-accent-terracotta">Visit Our Facilities </span>', $html);
        $this->assertMatchesRegularExpression('#href="/contact\?topic=Court%20Booking\#contact-form">Book a Court#', $html);
        $this->assertStringContainsString('Play<br>', $html);
    }
    public function test_the_contact_form_preselects_the_court_booking_topic(): void
    {
        $contact = Page::create(['title' => 'Contact', 'slug' => 'contact', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
        PageSection::create(['page_id' => $contact->id, 'type' => 'contact_form', 'sort_order' => 1, 'is_visible' => true, 'data' => []]);
        $this->get('/contact?topic=Court%20Booking')->assertOk()->assertSee('<option value="Court Booking" selected>', false);
    }
    public function test_the_home_facilities_strip_still_lists_the_shared_facility_records(): void
    {
        $home = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'home']);
        PageSection::create(['page_id' => $home->id, 'type' => 'split', 'sort_order' => 1, 'is_visible' => true, 'data' => ['eyebrow' => 'OUR FACILITIES', 'title' => 'Premium Spaces']]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<ul class="kl-amenities">', $html);
        $this->assertStringContainsString('<span>Player Lounge</span>', $html);
        $this->assertStringNotContainsString('kl-amenities-band', $html);
    }
    public function test_editors_can_edit_the_facilities_layouts(): void
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $showcase = $this->section('facilities_showcase', ['title' => 'Amenities']);
        $this->actingAs($editor)->get(route('admin.pages.sections.edit', [$this->page, $showcase]))->assertOk()
            ->assertSee('Photo beside the facilities as icon features (Facilities design)')
            ->assertSee('The facilities listed come from the published Facility records')
            ->assertSee('name="image"', false);
        $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $showcase]), ['title' => 'Amenities', 'layout' => 'amenities', 'image' => '/images/home/hero-player.jpg', 'image_alt' => 'Hall'])->assertSessionHasNoErrors();
        $this->assertSame(['amenities', '/images/home/hero-player.jpg', 'Hall'], [$showcase->fresh()->data['layout'], $showcase->fresh()->data['image'], $showcase->fresh()->data['image_alt']]);

        $split = $this->section('split', ['title' => 'Courts']);
        $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $split]), [
            'title' => 'Courts', 'layout' => 'courts',
            'items_json' => json_encode([['title' => 'Court Flooring', 'description' => 'Surfaces.', 'image' => 'https://images.example.test/floor.jpg', 'image_alt' => 'Floor', 'image_position' => 'bottom', 'url' => '/contact']]),
        ])->assertSessionHasNoErrors();
        $this->assertSame(['courts', 'bottom', '/contact'], [$split->fresh()->data['layout'], $split->fresh()->data['items'][0]['image_position'], $split->fresh()->data['items'][0]['url']]);
        $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $split]), ['title' => 'Courts', 'items_json' => json_encode([['title' => 'X', 'url' => 'javascript:alert(1)']])])->assertSessionHasErrors('items_json');

        $grid = $this->section('card_grid', ['title' => 'Spaces']);
        $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $grid]), ['layout' => 'spaces'])->assertSessionHasNoErrors();
        $this->assertSame('spaces', $grid->fresh()->data['layout']);
        $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $showcase]), ['layout' => 'bands'])->assertSessionHasErrors('layout');
    }
}

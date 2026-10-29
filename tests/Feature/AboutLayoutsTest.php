<?php
namespace Tests\Feature;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/**
 * The About page design (design-reference/Kelika Sports - About Page.png): opt-in section layouts (stats "highlights",
 * five_elements "lines", split "story_left", cta "journey") that leave the default layouts and other pages unchanged,
 * with unverified highlights kept from visitors.
 */
class AboutLayoutsTest extends TestCase
{
    use RefreshDatabase;
    private Page $page;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['kelika.demo_content.public' => false, 'kelika.demo_content.hosts' => []]);
        $this->page = Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
    }
    private function section(string $type, array $data, bool $visible = true): PageSection
    {
        return PageSection::create(['page_id' => $this->page->id, 'type' => $type, 'sort_order' => PageSection::count() + 1, 'is_visible' => $visible, 'data' => $data]);
    }
    private function html(): string
    {
        return $this->get('/about')->assertOk()->getContent();
    }
    private function highlights(bool $demo): PageSection
    {
        return $this->section('stats', ['layout' => 'highlights', 'title' => 'KELIKA at a glance', 'demo_content' => $demo, 'items' => [
            ['value' => '8+', 'label' => 'Badminton Courts'], ['value' => 'All Age Groups', 'label' => 'Kids to Adults'], ['value' => '', 'label' => 'Ignored'],
        ]]);
    }
    public function test_verified_highlights_render_as_a_light_band(): void
    {
        $this->highlights(false);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-highlights" aria-label="KELIKA at a glance">', $html);
        $this->assertMatchesRegularExpression('#<strong class="kl-highlight__value">8\+</strong>\s*<span class="kl-highlight__label">Badminton Courts</span>#', $html);
        $this->assertSame(2, substr_count($html, '<li class="kl-highlight">'));
        $this->assertStringNotContainsString('Ignored', $html);
        $this->assertStringNotContainsString('kl-stats', $html);
    }
    public function test_unverified_highlights_reach_only_demo_mode_and_preview(): void
    {
        $this->highlights(true);
        $html = $this->html();
        $this->assertStringNotContainsString('kl-highlights', $html);
        $this->assertStringNotContainsString('8+', $html);

        config(['kelika.demo_content.public' => true, 'kelika.demo_content.hosts' => [parse_url(config('app.url'), PHP_URL_HOST), 'localhost']]);
        $demo = $this->html();
        $this->assertStringContainsString('<strong class="kl-highlight__value">8+</strong>', $demo);
        $this->assertStringNotContainsString('Demo highlights', $demo);

        config(['kelika.demo_content.public' => false, 'kelika.demo_content.hosts' => []]);
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $preview = $this->actingAs($editor)->get(route('admin.pages.preview', $this->page))->assertOk()->getContent();
        $this->assertStringContainsString('<strong class="kl-highlight__value">8+</strong>', $preview);
        $this->assertStringContainsString('Demo highlights — unverified', $preview);
    }
    public function test_a_stats_section_without_the_highlights_layout_keeps_its_green_band(): void
    {
        $this->section('stats', ['items' => [['value' => '8+', 'label' => 'Courts']]]);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-stats kl-stats--image"', $html);
        $this->assertStringNotContainsString('kl-highlights', $html);
    }
    public function test_the_five_elements_lines_use_element_icons_colours_and_copy(): void
    {
        $this->section('five_elements', ['layout' => 'lines', 'eyebrow' => 'OUR PHILOSOPHY', 'title' => 'Guided by the|Five Elements', 'heading_accent' => 'terracotta', 'subtitle' => 'A balanced approach.', 'items' => [
            ['title' => 'Earth', 'keyword' => 'Stability', 'description' => 'Strong foundation and discipline.', 'color' => '#176b4e', 'icon' => 'leaf'],
            ['title' => 'Water', 'keyword' => 'Flow', 'description' => 'Adaptability.', 'color' => '#1687c9', 'icon' => '≈'],
        ]]);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-elements-lines" id="our-philosophy">', $html);
        $this->assertStringContainsString('<span class="d-block kl-accent-terracotta">Five Elements </span>', $html);
        $this->assertStringContainsString('<p class="kl-split-head__text">A balanced approach.</p>', $html);
        $this->assertStringContainsString('<li class="kl-element-line" style="--kl-item: #176b4e">', $html);
        $this->assertStringContainsString(\App\Support\Icon::svg('leaf', 'kl-icon kl-icon--xl'), $html);
        // A starter glyph is replaced by the design's element icon, never printed as text.
        $this->assertStringContainsString(\App\Support\Icon::svg('waves', 'kl-icon kl-icon--xl'), $html);
        $this->assertStringNotContainsString('≈', $html);
        $this->assertStringContainsString('<p class="kl-element-line__keyword">Stability</p>', $html);
    }
    public function test_story_sections_put_the_photo_on_either_side_without_a_panel(): void
    {
        $this->section('split', ['layout' => 'program', 'eyebrow' => 'OUR STORY', 'title' => 'A Passion for Sports.|A Vision for the Future.', 'heading_accent' => 'terracotta',
            'body' => 'KELIKA is built on a simple belief.', 'image' => '/images/home/hero-player.jpg', 'image_alt' => 'Indoor hall']);
        $this->section('split', ['layout' => 'story_left', 'eyebrow' => 'OUR PEOPLE', 'title' => 'Experienced Coaches.|Real Guidance.', 'body' => 'Our coaches.', 'image' => '/images/home/stats-shuttlecock.jpg', 'image_alt' => 'Coach']);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-feature-program kl-feature-program--no-panel"', $html);
        $this->assertStringContainsString('id="our-story"', $html);
        $this->assertStringContainsString('<section class="kl-section kl-feature-program kl-feature-program--no-panel kl-feature-program--reverse"', $html);
        $this->assertStringContainsString('id="our-people"', $html);
        $this->assertStringContainsString('<span class="d-block kl-accent-terracotta">A Vision for the Future. </span>', $html);
        $this->assertStringNotContainsString('kl-feature-program__panel', $html);
        $this->assertStringNotContainsString('kl-actions', $html, 'no buttons without labels');
        $this->assertSame(1, substr_count($html, 'kl-feature-program--reverse'));
    }
    public function test_the_journey_cta_has_both_buttons_and_is_opt_in(): void
    {
        $cta = $this->section('cta', ['tone' => 'green', 'eyebrow' => 'FIND YOUR PLACE AT KELIKA', 'title' => 'Be Part of the KELIKA Journey', 'subtitle' => 'There is a place for you.',
            'button_label' => 'Join KELIKA', 'button_url' => '/academy', 'secondary_button_label' => 'Contact Us', 'secondary_button_url' => '/contact', 'image' => '/images/home/stats-shuttlecock.jpg']);
        $this->assertStringNotContainsString('kl-cta--journey', $this->html());
        $cta->update(['data' => ['layout' => 'journey'] + $cta->data]);
        $html = $this->html();
        $this->assertStringContainsString('class="kl-cta kl-cta--green kl-cta--image kl-cta--journey "', $html);
        $this->assertMatchesRegularExpression('#class="btn btn-kelika-gold" href="/academy">Join KELIKA#', $html);
        $this->assertMatchesRegularExpression('#href="/contact"\s*>Contact Us#', $html);
        $this->assertStringContainsString('<p class="kl-kicker">FIND YOUR PLACE AT KELIKA</p>', $html);
    }
    public function test_the_complete_about_page_renders_its_sections_in_reference_order_and_hides_the_empty_team(): void
    {
        $this->section('hero', ['layout' => 'cinematic', 'eyebrow' => 'ABOUT KELIKA', 'title' => 'Driven by the Elements.|*Built for a Brighter Tomorrow.*', 'heading_accent' => 'terracotta',
            'image' => '/images/home/hero-player.jpg', 'button_label' => 'Our Story', 'button_url' => '#our-story']);
        $this->section('feature_list', ['layout' => 'strip', 'eyebrow' => 'OUR PURPOSE', 'title' => 'A Community|Built to Grow', 'heading_accent' => 'gold', 'items' => [
            ['title' => 'Our Mission', 'description' => 'Inspire.', 'icon' => 'target'], ['title' => 'Our Vision', 'icon' => 'eye'], ['title' => 'Our Values', 'icon' => 'gem'], ['title' => 'Our Community', 'icon' => 'users']]]);
        $this->highlights(false);
        $this->section('split', ['layout' => 'program', 'eyebrow' => 'OUR STORY', 'title' => 'A Passion for Sports.', 'image' => '/images/home/hero-player.jpg']);
        $this->section('five_elements', ['layout' => 'lines', 'title' => 'Guided by the|Five Elements', 'items' => [['title' => 'Earth']]]);
        $this->section('split', ['layout' => 'story_left', 'eyebrow' => 'OUR PEOPLE', 'title' => 'Experienced Coaches.', 'image' => '/images/home/hero-player.jpg']);
        $this->section('split', ['layout' => 'program', 'eyebrow' => 'OUR COMMITMENT', 'title' => 'Building a Stronger|Sports Community', 'button_label' => 'Join Our Community', 'button_url' => '/contact', 'image' => '/images/home/hero-player.jpg']);
        $this->section('team', ['eyebrow' => 'OUR PEOPLE', 'title' => 'EXPERIENCED COACHES.|REAL GUIDANCE.'], false);
        $this->section('cta', ['layout' => 'journey', 'tone' => 'green', 'title' => 'Be Part of the KELIKA Journey', 'button_label' => 'Join KELIKA', 'button_url' => '/academy', 'secondary_button_label' => 'Contact Us', 'secondary_button_url' => '/contact']);
        $html = $this->html();
        $this->assertMatchesRegularExpression('#kl-hero--cinematic.*?kl-why.*?kl-highlights.*?id="our-story".*?kl-elements-lines.*?id="our-people".*?id="our-commitment".*?kl-cta--journey.*?site-footer#s', $html);
        $this->assertMatchesRegularExpression('#href="\#our-story">\s*Our Story#', $html);
        $this->assertSame(1, substr_count($html, 'id="our-story"'));
        foreach (['eye', 'gem'] as $icon) $this->assertStringContainsString(\App\Support\Icon::svg($icon, 'kl-icon kl-icon--xl'), $html);
        $this->assertStringNotContainsString('EXPERIENCED COACHES.', $html);
        $this->assertMatchesRegularExpression('#href="/contact">\s*Join Our Community#', $html);
    }
    public function test_editors_can_choose_the_about_layouts(): void
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $cases = [
            ['stats', 'highlights', 'Light band of short highlights (About design)'],
            ['five_elements', 'lines', 'Heading row above large line icons in coloured columns (About design)'],
            ['split', 'story_left', 'Photo on the left fading into title-case copy (About design)'],
            ['cta', 'journey', 'Title-case heading on a green panel with an angled photo on the right (About design)'],
        ];
        foreach ($cases as [$type, $layout, $label]) {
            $section = $this->section($type, ['title' => 'Section']);
            $this->actingAs($editor)->get(route('admin.pages.sections.edit', [$this->page, $section]))->assertOk()->assertSee($label);
            $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $section]), ['title' => 'Edited', 'is_visible' => '1', 'layout' => $layout])->assertSessionHasNoErrors();
            $this->assertSame([$layout, 'Edited'], [$section->fresh()->data['layout'], $section->fresh()->data['title']]);
            $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $section]), ['layout' => 'bands'])->assertSessionHasErrors('layout');
        }
        // Highlights: items and the demo flag save from the form.
        $stats = $this->section('stats', ['title' => 'Glance']);
        $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $stats]), ['title' => 'Glance', 'is_visible' => '1', 'layout' => 'highlights', 'demo_content' => '1',
            'items_json' => json_encode([['value' => '8+', 'label' => 'Badminton Courts']])])->assertSessionHasNoErrors();
        $this->assertSame(['highlights', true, '8+'], [$stats->fresh()->data['layout'], $stats->fresh()->data['demo_content'], $stats->fresh()->data['items'][0]['value']]);
    }
}

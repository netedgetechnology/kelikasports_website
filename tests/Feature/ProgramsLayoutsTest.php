<?php
namespace Tests\Feature;
use App\Models\ContentItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/**
 * The Programs page design (design-reference/Kelika Sports - Structured Programs Page.png): opt-in section layouts that
 * leave the default layouts, and the program records shared with the Academy page, unchanged.
 */
class ProgramsLayoutsTest extends TestCase
{
    use RefreshDatabase;
    private Page $page;
    private string $enquiry;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->page = Page::create(['title' => 'Programs', 'slug' => 'programs', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
        ContentItem::create(['type' => 'program', 'title' => 'Kids Program', 'status' => 'published', 'sort_order' => 1, 'data' => ['age_range' => 'Ages 5–12', 'image' => '/images/home/hero-player.jpg', 'features' => [['title' => 'Fundamentals and technique']]]]);
        ContentItem::create(['type' => 'program', 'title' => 'Adult Coaching', 'status' => 'published', 'sort_order' => 2, 'data' => ['age_range' => 'Ages 18+', 'url' => '/academy']]);
        ContentItem::create(['type' => 'program', 'title' => 'Hidden Program', 'status' => 'draft', 'sort_order' => 3, 'data' => []]);
        $this->enquiry = e(route('page.show', ['slug' => 'contact', 'topic' => 'Programs Enquiry']).'#contact-form');
    }
    private function section(string $type, array $data): PageSection
    {
        return PageSection::create(['page_id' => $this->page->id, 'type' => $type, 'sort_order' => PageSection::count() + 1, 'is_visible' => true, 'data' => $data]);
    }
    private function html(): string
    {
        return $this->get('/programs')->assertOk()->getContent();
    }
    public function test_program_cards_highlight_the_first_program_and_link_each_card(): void
    {
        $this->section('program_list', ['layout' => 'cards', 'eyebrow' => '', 'title' => '']);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-program-cards" id="program-list">', $html);
        $this->assertStringContainsString('<h2 class="visually-hidden">Our programs</h2>', $html);
        $this->assertMatchesRegularExpression('#<li class="kl-program-card kl-program-card--featured">.*?Kids Program#s', $html);
        $this->assertSame(1, substr_count($html, 'kl-program-card--featured'));
        // Without a program URL a card opens the programs enquiry; with one it uses it. Highlights are not shown here.
        $this->assertStringContainsString('<a class="stretched-link" href="'.$this->enquiry.'">Kids Program</a>', $html);
        $this->assertStringContainsString('<a class="stretched-link" href="/academy">Adult Coaching</a>', $html);
        $this->assertStringContainsString('<p class="kl-program-card__meta">Ages 18+</p>', $html);
        $this->assertStringNotContainsString('Fundamentals and technique', $html);
        $this->assertStringNotContainsString('Hidden Program', $html);
    }
    public function test_program_cards_show_a_heading_when_one_is_set(): void
    {
        $this->section('program_list', ['layout' => 'cards', 'eyebrow' => 'OUR PROGRAMS', 'title' => 'Choose Your Path']);
        $html = $this->html();
        $this->assertStringContainsString('<p class="kl-kicker">OUR PROGRAMS</p>', $html);
        $this->assertStringNotContainsString('visually-hidden">Our programs', $html);
    }
    public function test_the_featured_program_shows_both_buttons_the_photo_and_the_highlights_panel(): void
    {
        $this->section('split', ['layout' => 'program', 'eyebrow' => 'KIDS PROGRAM|AGES 5 – 12', 'title' => 'Build a Love|for the Game', 'heading_accent' => 'terracotta',
            'body' => 'Our Kids Program introduces badminton.', 'button_label' => 'Join Kids Program', 'button_url' => '/contact?topic=Programs%20Enquiry#contact-form',
            'secondary_button_label' => 'Book a Trial', 'image' => '/images/home/hero-player.jpg', 'image_alt' => 'Young player',
            'items' => [['title' => 'Fun & Engaging Training', 'description' => 'Game-based learning', 'icon' => 'star'], ['title' => 'Fundamental Skills', 'icon' => 'shuttle', 'color' => '#123456'], ['title' => '']]]);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-feature-program"', $html);
        $this->assertStringContainsString('id="kids-program"', $html);
        $this->assertMatchesRegularExpression('#<span>KIDS PROGRAM</span>\s*<span class="kl-feature-program__meta">AGES 5 – 12</span>#', $html);
        $this->assertStringContainsString('<span class="d-block kl-accent-terracotta">for the Game </span>', $html);
        $this->assertMatchesRegularExpression('#href="/contact\?topic=Programs%20Enquiry\#contact-form">\s*Join Kids Program#', $html);
        // A secondary button without a URL falls back to the contact page.
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('page.show', ['slug' => 'contact']), '#').'">\s*Book a Trial#', $html);
        $this->assertStringContainsString('alt="Young player"', $html);
        $this->assertSame(2, substr_count($html, 'class="kl-feature-program__item"'));
        $this->assertStringContainsString('style="--kl-item: #123456"', $html);
        $this->assertStringContainsString('<p class="kl-feature-program__item-text">Game-based learning</p>', $html);
    }
    public function test_the_featured_program_without_an_image_or_items_drops_those_parts(): void
    {
        $this->section('split', ['layout' => 'program', 'title' => 'Build a Love', 'video_url' => 'https://example.com/video']);
        $html = $this->html();
        $this->assertStringContainsString('kl-feature-program--no-media kl-feature-program--no-panel', $html);
        $this->assertStringNotContainsString('kl-feature-program__media', $html);
        $this->assertStringNotContainsString('kl-feature-program__panel', $html);
    }
    public function test_a_split_without_the_program_layout_keeps_its_template(): void
    {
        $this->section('split', ['title' => 'Coaching', 'secondary_button_label' => 'Book a Trial', 'items' => [['title' => 'Panel item']]]);
        $html = $this->html();
        $this->assertStringContainsString('kl-split kl-split--image_right', $html);
        $this->assertStringNotContainsString('kl-feature-program', $html);
        $this->assertStringNotContainsString('Panel item', $html);
    }
    public function test_numbered_features_count_their_cards(): void
    {
        $this->section('feature_list', ['layout' => 'numbered', 'eyebrow' => 'PROGRAM DETAILS', 'title' => '*What* Your Child Will Learn', 'body' => 'Our structured curriculum.', 'items' => [
            ['title' => 'Basic Techniques', 'description' => 'Grip and stance.', 'icon' => 'shuttle'], ['title' => 'Physical Fitness', 'icon' => 'shoe'],
        ]]);
        $html = $this->html();
        $this->assertStringContainsString('<span class="kl-heading__highlight">What</span>', $html);
        $this->assertStringContainsString('<p class="kl-split-head__text">Our structured curriculum.</p>', $html);
        $this->assertMatchesRegularExpression('#Basic Techniques.*?Physical Fitness#s', $html);
        $this->assertStringContainsString('<span class="kl-learn__number" aria-hidden="true">01</span>', $html);
        $this->assertStringContainsString('<span class="kl-learn__number" aria-hidden="true">02</span>', $html);
        $this->assertStringNotContainsString('kl-icon-text', $html);
    }
    public function test_option_cards_turn_each_description_line_into_a_checklist_point(): void
    {
        $this->section('feature_list', ['layout' => 'options', 'title' => 'Flexible Options|for Every Family', 'body' => 'Batches through the week.', 'items' => [
            ['title' => 'Group Training', 'description' => "Small batch sizes\r\nStructured curriculum\n\n", 'icon' => 'users'],
            ['title' => 'Weekend Batches', 'description' => 'Ideal for school students|Beginner to advanced levels', 'icon' => 'calendar'],
        ]]);
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-options" id="program-schedule">', $html);
        $this->assertStringContainsString('<p class="kl-options__text">Batches through the week.</p>', $html);
        $this->assertSame(4, substr_count($html, '<li><svg class="kl-icon"'));
        foreach (['Small batch sizes', 'Structured curriculum', 'Ideal for school students', 'Beginner to advanced levels'] as $point) $this->assertStringContainsString('<span>'.$point.'</span>', $html);
    }
    public function test_the_pathway_puts_an_arrow_between_stages_and_links_only_stages_with_a_url(): void
    {
        $this->section('card_grid', ['layout' => 'pathway', 'eyebrow' => 'TRAINING STRUCTURE', 'title' => 'A Clear Pathway|for Growth', 'subtitle' => 'A progression pathway.', 'items' => [
            ['title' => 'Foundation', 'description' => 'Basic skills.', 'image' => '/images/home/hero-player.jpg', 'image_alt' => 'Foundation stage'],
            ['title' => 'Kids Program', 'description' => 'Reuses the program image.'],
            ['title' => 'Competitive', 'description' => 'Tournament preparation.', 'url' => '/events'],
            ['title' => ''],
        ]]);
        $html = $this->html();
        $this->assertStringContainsString('id="training-structure"', $html);
        $this->assertStringContainsString('<p class="kl-split-head__text">A progression pathway.</p>', $html);
        $this->assertSame(3, substr_count($html, '<li class="kl-stage">'));
        $this->assertSame(2, substr_count($html, 'class="kl-stage__arrow"'));
        $this->assertStringContainsString('alt="Foundation stage"', $html);
        $this->assertSame(2, substr_count($html, '/images/home/hero-player.jpg'));
        $this->assertStringContainsString('<a class="stretched-link" href="/events">Competitive</a>', $html);
        $this->assertStringContainsString('<h3 class="kl-stage__title">Foundation</h3>', $html);
    }
    public function test_the_program_cta_is_opt_in(): void
    {
        $cta = $this->section('cta', ['tone' => 'light', 'title' => 'Join the Kids Program', 'button_label' => 'Book a Trial', 'secondary_button_label' => 'Contact Us', 'secondary_button_url' => '/contact', 'script_text' => 'Small|Steps']);
        $this->assertStringNotContainsString('kl-cta--program', $this->html());

        $cta->update(['data' => ['layout' => 'program'] + $cta->data]);
        $html = $this->html();
        $this->assertStringContainsString('class="kl-cta kl-cta--light  kl-cta--program "', $html);
        $this->assertStringContainsString('Small<br>', $html);
        $this->assertMatchesRegularExpression('#href="/contact"\s*>Contact Us#', $html);
    }
    public function test_the_hero_strip_takes_five_highlights_and_book_a_trial_opens_the_enquiry(): void
    {
        $this->section('hero', ['layout' => 'cinematic', 'title' => 'Structured Programs|*for Every Stage*', 'heading_accent' => 'terracotta', 'image' => '/images/home/hero-player.jpg',
            'button_label' => 'Explore Programs', 'button_url' => '#program-list', 'secondary_button_label' => 'Book a Trial', 'secondary_button_url' => '/contact?topic=Programs%20Enquiry#contact-form',
            'items' => [['title' => 'Professional|Coaching', 'icon' => 'coach'], ['title' => 'Age-specific|Programs', 'icon' => 'users'], ['title' => 'Structured|Curriculum', 'icon' => 'grid'], ['title' => 'Progress|Tracking', 'icon' => 'chart'], ['title' => 'Competition|Opportunities', 'icon' => 'trophy']]]);
        $html = $this->html();
        $this->assertStringContainsString('kl-hero__features kl-hero__features--five', $html);
        $this->assertStringContainsString('<span class="kl-hero__highlight kl-accent-terracotta">for Every Stage</span>', $html);
        $this->assertMatchesRegularExpression('#href="\#program-list">\s*Explore Programs#', $html);
        $this->assertMatchesRegularExpression('#href="/contact\?topic=Programs%20Enquiry\#contact-form"\s*>\s*Book a Trial#', $html);
    }
    public function test_the_contact_form_preselects_the_programs_enquiry_topic(): void
    {
        $contact = Page::create(['title' => 'Contact', 'slug' => 'contact', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
        PageSection::create(['page_id' => $contact->id, 'type' => 'contact_form', 'sort_order' => 1, 'is_visible' => true, 'data' => []]);
        $this->get('/contact?topic=Programs%20Enquiry')->assertOk()->assertSee('<option value="Programs Enquiry" selected>', false);
    }
    public function test_the_academy_detailed_layout_still_shows_the_shared_program_highlights(): void
    {
        $academy = Page::create(['title' => 'Academy', 'slug' => 'academy', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
        PageSection::create(['page_id' => $academy->id, 'type' => 'program_list', 'sort_order' => 1, 'is_visible' => true, 'data' => ['layout' => 'detailed', 'title' => 'Programs']]);
        $html = $this->get('/academy')->assertOk()->getContent();
        $this->assertStringContainsString('<span>Fundamentals and technique</span>', $html);
        $this->assertStringNotContainsString('kl-program-card', $html);
    }
    public function test_editors_can_choose_the_programs_layouts_and_edit_featured_program_highlights(): void
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $split = $this->section('split', ['title' => 'Kids']);
        $this->actingAs($editor)->get(route('admin.pages.sections.edit', [$this->page, $split]))->assertOk()
            ->assertSee('Featured program: copy, photo and a highlights panel (Programs design)')
            ->assertSee('id="secondary_button_label"', false)
            ->assertSee('id="items-editor" data-section-type="split"', false);
        $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $split]), [
            'title' => 'Kids', 'layout' => 'program', 'secondary_button_label' => 'Book a Trial', 'secondary_button_url' => '/contact',
            'items_json' => json_encode([['title' => 'Fundamental Skills', 'description' => 'Grip, footwork, strokes', 'icon' => 'shuttle']]),
        ])->assertSessionHasNoErrors();
        $data = $split->fresh()->data;
        $this->assertSame(['program', 'Book a Trial', 'Fundamental Skills'], [$data['layout'], $data['secondary_button_label'], $data['items'][0]['title']]);

        foreach (['program_list' => 'cards', 'feature_list' => 'options', 'card_grid' => 'pathway', 'cta' => 'program'] as $type => $layout) {
            $section = $this->section($type, ['title' => 'Section']);
            $url = route('admin.pages.sections.update', [$this->page, $section]);
            $this->actingAs($editor)->put($url, ['layout' => $layout])->assertSessionHasNoErrors();
            $this->assertSame($layout, $section->fresh()->data['layout']);
            $this->actingAs($editor)->put($url, ['layout' => 'bands'])->assertSessionHasErrors('layout');
        }
    }
}

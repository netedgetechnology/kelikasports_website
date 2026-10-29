<?php
namespace Tests\Feature;
use App\Models\ContentItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/**
 * The Academy page design (design-reference/Kelika Sports - Academy Page.png): opt-in section layouts, hero figures and
 * unverified (demo) content that visitors outside development demo mode never see.
 */
class AcademyLayoutsTest extends TestCase
{
    use RefreshDatabase;
    private Page $page;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->page = Page::create(['title' => 'Academy', 'slug' => 'academy', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
        $this->section('hero', ['layout' => 'cinematic', 'title' => 'KELIKA|*ACADEMY*', 'heading_accent' => 'terracotta', 'image' => '/images/home/hero-player.jpg', 'demo_content' => true, 'items' => [
            ['value' => '500+', 'title' => 'Active Members', 'icon' => 'users'], ['title' => 'Expert|Coaching', 'icon' => 'coach'],
        ]]);
        ContentItem::create(['type' => 'program', 'title' => 'Kids Program', 'status' => 'published', 'sort_order' => 1, 'data' => ['age_range' => 'Ages 5–12', 'features' => [['title' => 'Fundamentals and technique'], ['title' => '']]]]);
    }
    private function section(string $type, array $data): PageSection
    {
        return PageSection::create(['page_id' => $this->page->id, 'type' => $type, 'sort_order' => PageSection::count() + 1, 'is_visible' => true, 'data' => $data]);
    }
    private function html(): string
    {
        return $this->get('/academy')->assertOk()->getContent();
    }
    public function test_unverified_hero_figures_are_hidden_from_visitors_but_other_highlights_stay(): void
    {
        $html = $this->html();
        $this->assertStringNotContainsString('500+', $html);
        $this->assertStringNotContainsString('kl-hero__features--figures', $html);
        $this->assertStringContainsString('Expert<br>', $html);
    }
    public function test_hero_figures_show_in_demo_mode_and_once_verified(): void
    {
        config(['kelika.demo_content.public' => true, 'kelika.demo_content.hosts' => [parse_url(config('app.url'), PHP_URL_HOST), 'localhost']]);
        $this->assertMatchesRegularExpression('#<span class="kl-hero__figure"><strong>500\+</strong> <span>Active Members</span></span>#', $this->html());

        config(['kelika.demo_content.hosts' => []]);
        PageSection::where('type', 'hero')->first()->update(['data' => ['demo_content' => false] + PageSection::where('type', 'hero')->first()->data]);
        $this->assertStringContainsString('kl-hero__features kl-hero__features--figures', $this->html());
    }
    public function test_hero_figures_show_in_cms_preview_with_a_note(): void
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $html = $this->actingAs($editor)->get(route('admin.pages.preview', $this->page))->assertOk()->getContent();
        $this->assertStringContainsString('<strong>500+</strong>', $html);
        $this->assertStringContainsString('Demo figures — unverified', $html);
    }
    public function test_the_academy_layouts_render_their_content(): void
    {
        $this->section('feature_list', ['layout' => 'panel', 'title' => 'More Than|Badminton Training', 'body' => 'Intro copy.', 'button_label' => 'Our Philosophy', 'button_url' => '/about', 'items' => [['title' => 'Our Mission', 'description' => 'Develop athletes.', 'icon' => 'target']]]);
        $this->section('feature_list', ['layout' => 'row', 'title' => '*Expert Coaching*|for *Lifelong Growth*', 'items' => [['title' => 'Mental Strength', 'icon' => 'brain', 'color' => '#2b6f8e']]]);
        $this->section('program_list', ['layout' => 'detailed', 'title' => 'Programs', 'button_label' => 'View All Programs', 'button_url' => '/programs']);
        $this->section('card_grid', ['layout' => 'featured', 'eyebrow' => 'OUR FACILITIES', 'title' => 'Premium Spaces', 'items' => [['title' => 'Player Lounge', 'url' => '/facilities', 'icon' => 'sofa']]]);
        $this->section('five_elements', ['layout' => 'cards', 'title' => 'Five Elements', 'items' => [['title' => 'Earth', 'keyword' => 'Foundation', 'icon' => 'mountain', 'color' => '#a4602e', 'image' => '/images/academy/element-earth.svg']]]);
        $html = $this->html();

        $this->assertStringContainsString('class="kl-section kl-about kl-about--no-media"', $html);
        $this->assertMatchesRegularExpression('#<a class="btn btn-kelika" href="/about">\s*Our Philosophy <svg#', $html);
        $this->assertStringContainsString('<h3 class="kl-about__title">Our Mission</h3>', $html);
        $this->assertStringContainsString('style="--kl-item: #2b6f8e"', $html);
        $this->assertStringContainsString('<span class="kl-heading__highlight">Lifelong Growth</span>', $html);
        $this->assertStringContainsString('<li>'.\App\Support\Icon::svg('check', 'kl-icon').'<span>Fundamentals and technique</span></li>', $html);
        $this->assertSame(1, substr_count($html, '<li><svg class="kl-icon"'), 'empty highlights are skipped');
        $this->assertStringContainsString('href="'.route('page.show', ['slug' => 'programs']).'#program-list" aria-label="Learn more about Kids Program"', $html);
        $this->assertStringContainsString('id="facility-list"', $html);
        $this->assertStringContainsString('<span class="kl-facility__icon">', $html, 'a card without an image shows its icon');
        $this->assertStringContainsString('src="/images/academy/element-earth.svg"', $html);
        $this->assertStringContainsString('<h3 class="kl-element__name">Earth</h3>', $html);
    }
    public function test_demo_cards_are_hidden_from_visitors(): void
    {
        $this->section('card_grid', ['layout' => 'featured', 'title' => 'Facilities', 'items' => [['title' => 'Pro Shop', 'demo' => true], ['title' => 'Player Lounge']]]);
        $html = $this->html();
        $this->assertStringNotContainsString('Pro Shop', $html);
        $this->assertStringContainsString('Player Lounge', $html);

        config(['kelika.demo_content.public' => true, 'kelika.demo_content.hosts' => [parse_url(config('app.url'), PHP_URL_HOST), 'localhost']]);
        $this->assertStringContainsString('Pro Shop', $this->html());
    }
    public function test_sections_without_a_layout_keep_their_original_templates(): void
    {
        $this->section('feature_list', ['title' => 'Why KELIKA', 'items' => [['title' => 'Coaches']]]);
        $this->section('program_list', ['title' => 'Programs']);
        $this->section('card_grid', ['title' => 'Cards', 'items' => [['title' => 'Card']]]);
        $this->section('five_elements', ['title' => 'Elements', 'items' => [['title' => 'Earth']]]);
        $html = $this->html();

        foreach (['kl-features', 'class="soft-card"', 'kl-section kl-cards', 'feature-icon mx-auto'] as $original) $this->assertStringContainsString($original, $html);
        foreach (['kl-about', 'kl-benefits', 'kl-programs', 'kl-showcase', 'kl-elements'] as $academy) $this->assertStringNotContainsString($academy, $html);
    }
    public function test_editors_can_choose_the_academy_layouts_and_unknown_ones_are_rejected(): void
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $section = $this->section('five_elements', ['title' => 'Elements']);
        $url = route('admin.pages.sections.update', [$this->page, $section]);

        $this->actingAs($editor)->put($url, ['layout' => 'cards'])->assertSessionHasNoErrors();
        $this->assertSame('cards', $section->fresh()->data['layout']);
        $this->actingAs($editor)->put($url, ['layout' => 'panel'])->assertSessionHasErrors('layout');
    }
}

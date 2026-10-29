<?php
namespace Tests\Feature;
use App\Models\ContentItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/**
 * The Sports page design (design-reference/Kelika Sports - Sports Page.png): sport bands, the "more sports" teaser, the
 * "which sport" guide and a five-item hero highlight strip. Sections without these layouts keep their templates.
 */
class SportsLayoutsTest extends TestCase
{
    use RefreshDatabase;
    private Page $page;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->page = Page::create(['title' => 'Sports', 'slug' => 'sports', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
        ContentItem::create(['type' => 'sport', 'title' => 'Badminton', 'status' => 'published', 'sort_order' => 1, 'data' => ['tagline' => 'SKILL. SPEED. STRATEGY.', 'description' => 'At the heart of Kelika.', 'url' => '/academy', 'image' => '/images/home/hero-player.jpg', 'features' => [['title' => 'For All Ages', 'description' => 'Kids to adults', 'icon' => 'users'], ['title' => '']]]]);
        ContentItem::create(['type' => 'sport', 'title' => 'Football', 'status' => 'published', 'sort_order' => 2, 'data' => ['features' => [['title' => 'Skill Development', 'icon' => 'football']]]]);
        ContentItem::create(['type' => 'sport', 'title' => 'Hidden Sport', 'status' => 'draft', 'sort_order' => 3, 'data' => []]);
    }
    private function section(string $type, array $data): PageSection
    {
        return PageSection::create(['page_id' => $this->page->id, 'type' => $type, 'sort_order' => PageSection::count() + 1, 'is_visible' => true, 'data' => $data]);
    }
    private function html(): string
    {
        return $this->get('/sports')->assertOk()->getContent();
    }
    public function test_sport_bands_show_each_published_sport_with_its_features_and_links(): void
    {
        $this->section('sport_directory', ['layout' => 'bands', 'title' => 'FIND YOUR GAME']);
        $html = $this->html();

        $this->assertStringContainsString('<section class="kl-sports" id="sports-list">', $html);
        $this->assertStringContainsString('class="kl-sport kl-sport--featured"', $html);
        $this->assertStringContainsString('class="kl-sport kl-sport--reverse"', $html);
        $this->assertStringContainsString('<h3 class="kl-sport__title">Badminton</h3>', $html);
        $this->assertStringContainsString('<p class="kl-sport__tagline">SKILL. SPEED. STRATEGY.</p>', $html);
        $this->assertStringContainsString('<h4 class="kl-sport__feature-title">For All Ages</h4>', $html);
        $this->assertSame(2, substr_count($html, 'class="kl-sport__feature"'), 'empty features are skipped');
        $this->assertMatchesRegularExpression('#<a class="btn btn-kelika" href="/academy">\s*Explore Badminton <svg#', $html);
        // Without a sport link, the button opens the contact form on a programs enquiry (no invented URLs).
        $this->assertMatchesRegularExpression('#href="'.preg_quote(e(route('page.show', ['slug' => 'contact', 'topic' => 'Programs Enquiry']).'#contact-form'), '#').'">\s*Enquire About Football <svg#', $html);
        $this->assertStringNotContainsString('Hidden Sport</h3>', $html);
        $this->assertStringContainsString('<h2 class="visually-hidden">', $html);
    }
    public function test_the_teaser_numbers_itself_after_the_sports_and_shows_its_cards(): void
    {
        $this->section('card_grid', ['layout' => 'teaser', 'eyebrow' => 'EXPANDING SOON', 'title' => 'MORE SPORTS', 'subtitle' => 'Exciting opportunities ahead.', 'body' => 'More sports soon.', 'items' => [
            ['title' => 'Tennis', 'description' => 'Availability to be confirmed.', 'image' => '/images/sports/tennis.svg'], ['title' => 'Athletics', 'icon' => 'star'],
        ]]);
        $html = $this->html();

        $this->assertMatchesRegularExpression('#<span class="kl-sport__number" aria-hidden="true">03</span>#', $html);
        $this->assertStringContainsString('<p class="kl-sport__tagline">Exciting opportunities ahead.</p>', $html);
        $this->assertStringContainsString('src="/images/sports/tennis.svg"', $html);
        $this->assertStringContainsString('<h3 class="kl-teaser__title">Athletics</h3>', $html);
        $this->assertStringContainsString('<span class="kl-facility__icon">', $html, 'a card without an image shows its icon');
    }
    public function test_the_guide_highlights_its_heading_and_links_its_button(): void
    {
        $this->section('feature_list', ['layout' => 'guide', 'title' => 'WHICH SPORT|IS *RIGHT FOR YOU?*', 'body' => 'Every athlete is unique.', 'button_label' => 'Talk to Our Team', 'button_url' => '/contact', 'items' => [['title' => 'Build Skills', 'icon' => 'target'], ['title' => 'Stay Active', 'icon' => 'heart', 'color' => '#cf6d50']]]);
        $html = $this->html();

        $this->assertStringContainsString('<span class="kl-heading__highlight">RIGHT FOR YOU?</span>', $html);
        $this->assertMatchesRegularExpression('#<a class="btn btn-kelika" href="/contact">\s*Talk to Our Team <svg#', $html);
        $this->assertStringContainsString('style="--kl-item: #cf6d50"', $html);
        $this->assertStringContainsString('<h3 class="kl-guide__item-title">Build Skills</h3>', $html);
    }
    public function test_a_five_item_hero_strip_gets_its_own_row_modifier(): void
    {
        $items = array_map(fn ($t) => ['title' => $t, 'icon' => 'star'], ['A', 'B', 'C', 'D', 'E']);
        $hero = $this->section('hero', ['layout' => 'cinematic', 'title' => 'Different Sports.', 'image' => '/images/home/hero-player.jpg', 'items' => $items]);
        $this->assertStringContainsString('kl-hero__features kl-hero__features--five', $this->html());

        $hero->update(['data' => ['items' => array_slice($items, 0, 4)] + $hero->data]);
        $this->assertStringNotContainsString('kl-hero__features--five', $this->html());
    }
    public function test_a_sport_directory_without_a_layout_keeps_the_editorial_template(): void
    {
        $this->section('sport_directory', ['title' => 'FIND YOUR GAME']);
        $html = $this->html();
        $this->assertStringContainsString('class="sport-editorial', $html);
        $this->assertStringNotContainsString('kl-sports', $html);
    }
    public function test_editors_can_choose_the_bands_layout_and_unknown_ones_are_rejected(): void
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $section = $this->section('sport_directory', ['title' => 'Sports']);
        $url = route('admin.pages.sections.update', [$this->page, $section]);

        $this->actingAs($editor)->get(route('admin.pages.sections.edit', [$this->page, $section]))->assertOk()->assertSee('Full-width bands with a features panel (Sports design)');
        $this->actingAs($editor)->put($url, ['layout' => 'bands'])->assertSessionHasNoErrors();
        $this->assertSame('bands', $section->fresh()->data['layout']);
        $this->actingAs($editor)->put($url, ['layout' => 'cards'])->assertSessionHasErrors('layout');
    }
}

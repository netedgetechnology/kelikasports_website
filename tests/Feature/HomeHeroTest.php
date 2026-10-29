<?php
namespace Tests\Feature;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Setting;
use App\Models\User;
use App\Support\SectionDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/** The homepage hero's "cinematic" layout (design-reference/KelikaSportsHeroBanner.png), and other heroes left as they were. */
class HomeHeroTest extends TestCase
{
    use RefreshDatabase;
    private const HERO = [
        'eyebrow' => 'DISCIPLINE  |  HEALTH  |  COMMUNITY', 'layout' => 'cinematic', 'title' => 'A STRONGER|*YOU*|THROUGH SPORT.', 'heading_accent' => 'gold',
        'subtitle' => 'World-class badminton training, modern facilities and a vibrant sports community for all ages.',
        'image' => '/images/home/hero-player.jpg', 'mobile_image' => '/images/home/hero-player-mobile.jpg', 'image_position' => '70% center',
        'image_alt' => 'Badminton player in a green shirt leaping for an overhead shot on an indoor court',
        'button_label' => 'Join Academy', 'button_url' => '/academy', 'secondary_button_label' => 'Book a Court', 'secondary_button_url' => '',
        'script_text' => 'More|Than|A Sport', 'values_text' => 'TRAIN|COMPETE|GROW|BELONG',
        'items' => [['title' => 'Expert|Coaching', 'icon' => 'coach'], ['title' => 'World-Class|Facilities', 'icon' => 'court'], ['title' => 'Programs|for All Ages', 'icon' => 'trophy'], ['title' => 'A Vibrant|Community', 'icon' => 'users']],
    ];
    private PageSection $hero;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Setting::write('site_name', 'KELIKA');
        $home = $this->page('home', 'Home');
        $this->hero = PageSection::create(['page_id' => $home->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => self::HERO]);
    }
    private function page(string $slug, string $title, string $status = 'published'): Page
    {
        return Page::create(['title' => $title, 'slug' => $slug, 'status' => $status, 'published_at' => $status === 'published' ? now()->subDay() : null, 'robots_directive' => 'index,follow', 'template' => 'standard', 'show_in_menu' => true]);
    }
    /** The hero <section> of a public page. */
    private function hero(string $uri = '/'): string
    {
        $html = $this->get($uri)->assertOk()->getContent();
        $start = strpos($html, '<section class="kl-hero');
        $this->assertNotFalse($start, 'hero rendered');
        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }
    private function heroData(array $changes): void
    {
        $this->hero->update(['data' => array_replace($this->hero->data, $changes)]);
    }

    // Layout and headline

    public function test_the_homepage_hero_uses_the_cinematic_layout_with_a_gold_highlight(): void
    {
        $hero = $this->hero();
        $this->assertStringContainsString('<section class="kl-hero kl-hero--cinematic">', $hero);
        $this->assertStringContainsString('<div class="kl-hero__decor" aria-hidden="true"></div>', $hero);
        $this->assertStringContainsString('<div class="kl-hero__shade" aria-hidden="true"></div>', $hero);
        $this->assertMatchesRegularExpression('#<h1 class="kl-hero__title">\s*<span>A STRONGER\s*</span>\s*<span><span class="kl-hero__highlight kl-accent-gold">YOU</span>\s*</span>\s*<span>THROUGH SPORT\.\s*</span>\s*</h1>#', $hero);
        $this->assertStringContainsString('<p class="kl-kicker">DISCIPLINE  |  HEALTH  |  COMMUNITY</p>', $hero);
        $this->assertStringContainsString('World-class badminton training, modern facilities and a vibrant sports community for all ages.', $hero);
        $this->assertStringNotContainsString('kl-hero__panel', $hero, 'the side panel of the standard layout is not shown');
        $this->assertStringNotContainsString('More', $hero);
    }
    public function test_the_four_benefits_are_listed_with_their_icons(): void
    {
        $hero = $this->hero();
        preg_match_all('#<li>\s*(<svg[^>]*class="kl-icon kl-icon--lg"[^>]*>).*?<span>(.*?)</span>\s*</li>#s', $hero, $m);
        $this->assertSame(['Expert<br> Coaching', 'World-Class<br> Facilities', 'Programs<br> for All Ages', 'A Vibrant<br> Community'], array_map(fn ($t) => trim(preg_replace('/\s+/', ' ', $t)), $m[2]));
        $this->assertStringContainsString('<ul class="kl-hero__features" aria-label="Highlights">', $hero);
        $this->assertStringNotContainsString('kl-icon-text', $hero, 'every benefit has a real icon, never its name as text');
    }
    public function test_highlight_markers_are_parsed_strictly(): void
    {
        $this->assertSame([['text' => 'YOU', 'highlight' => true]], SectionDefaults::highlights('*YOU*'));
        $this->assertSame([['text' => 'A ', 'highlight' => false], ['text' => 'STRONGER', 'highlight' => true], ['text' => ' YOU', 'highlight' => false]], SectionDefaults::highlights('A *STRONGER* YOU'));
        foreach (['5 * 3 = *x', '**', '* spaced *', 'no markers'] as $plain) $this->assertSame([['text' => $plain, 'highlight' => false]], SectionDefaults::highlights($plain), $plain);
    }
    public function test_highlighted_and_plain_title_text_is_escaped(): void
    {
        $this->heroData(['title' => '<b>BOLD</b>|*<script>alert(1)</script>*|"Q"&A', 'heading_accent' => 'terracotta']);
        $hero = $this->hero();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $hero);
        $this->assertStringNotContainsString('<b>BOLD</b>', $hero);
        $this->assertStringContainsString('<span class="kl-hero__highlight kl-accent-terracotta">&lt;script&gt;alert(1)&lt;/script&gt;</span>', $hero);
        $this->assertStringContainsString('&lt;b&gt;BOLD&lt;/b&gt;', $hero);
        $this->assertStringContainsString('&quot;Q&quot;&amp;A', $hero);
        // An unknown accent value falls back to gold rather than being written into a class name.
        $this->heroData(['title' => '*YOU*', 'heading_accent' => 'x" onmouseover="alert(1)']);
        $this->assertStringContainsString('<span class="kl-hero__highlight kl-accent-gold">YOU</span>', $this->hero());
    }

    // Buttons

    public function test_join_academy_follows_the_cms_link_and_book_a_court_opens_the_contact_form_on_court_booking(): void
    {
        $hero = $this->hero();
        $this->assertMatchesRegularExpression('#<a class="btn btn-kelika"\s+href="/academy">\s*Join Academy <svg#', $hero);
        $this->assertMatchesRegularExpression('#<a class="btn btn-kelika-outline"\s+href="'.preg_quote(route('page.show', ['slug' => 'contact', 'topic' => 'Court Booking']).'#contact-form', '#').'"\s*>\s*Book a Court <svg#', $hero);
        $this->assertStringContainsString('contact?topic=Court%20Booking#contact-form', $hero);
        $this->assertStringNotContainsString('target="_blank"', $hero);
        // Without a CMS link, Join Academy still goes to the Academy page.
        $this->heroData(['button_url' => '']);
        $this->assertStringContainsString('href="'.route('page.show', ['slug' => 'academy']).'"', $this->hero());
    }
    public function test_a_configured_booking_url_still_takes_precedence(): void
    {
        Setting::write('booking_url', 'https://booking.example.test/kelika', 'general');
        $this->assertMatchesRegularExpression('#href="https://booking\.example\.test/kelika"\s+target="_blank" rel="noopener noreferrer"\s*>\s*Book a Court#', $this->hero());
        $this->heroData(['secondary_button_url' => '/contact']);
        $this->assertMatchesRegularExpression('#href="/contact"\s*>\s*Book a Court#', $this->hero(), 'an explicit CMS link wins');
    }
    public function test_the_contact_form_preselects_only_a_known_topic(): void
    {
        $contact = $this->page('contact', 'Contact');
        PageSection::create(['page_id' => $contact->id, 'type' => 'contact_form', 'sort_order' => 1, 'is_visible' => true, 'data' => []]);
        $this->get('/contact?topic=Court%20Booking')->assertOk()->assertSee('<option value="Court Booking" selected>', false)->assertSee('id="contact-form"', false);
        foreach (['', '?topic=Nonsense', '?topic=%3Cscript%3E', '?topic[]=Court%20Booking', '?topic=court%20booking'] as $query) {
            $this->assertStringNotContainsString('selected>', $this->get('/contact'.$query)->assertOk()->getContent(), $query);
        }
        // A topic chosen by the visitor (after a validation error) wins over the link's.
        $this->withSession(['_old_input' => ['category' => 'Membership']])->get('/contact?topic=Court%20Booking')
            ->assertSee('<option value="Membership" selected>', false)->assertDontSee('<option value="Court Booking" selected>', false);
    }

    // Images

    public function test_separate_responsive_photographs_are_served_without_layout_shift(): void
    {
        $hero = $this->hero();
        $this->assertDoesNotMatchRegularExpression('#KelikaSportsHeroBanner|design-reference#', $hero, 'the reference screenshot is never used');
        $this->assertStringContainsString('<source media="(max-width: 767px)" type="image/webp" srcset="/images/optimized/home/hero-player-mobile/', $hero);
        $this->assertStringContainsString('<source media="(max-width: 767px)" srcset="/images/home/hero-player-mobile.jpg"', $hero);
        $this->assertStringContainsString('<source type="image/webp" srcset="/images/optimized/home/hero-player/', $hero);
        $this->assertStringContainsString('sizes="(min-width: 1200px) 64vw, (min-width: 992px) 70vw, 100vw"', $hero);
        $this->assertMatchesRegularExpression('#<img\s+class="kl-hero__image"\s+src="/images/home/hero-player\.jpg"[^>]*alt="Badminton player in a green shirt leaping for an overhead shot on an indoor court"[^>]*style="--kl-hero-pos: 70% center"[^>]*width="2100" height="1400"[^>]*fetchpriority="high"#', $hero);
        $this->assertStringNotContainsString('loading="lazy"', $hero, 'the hero image loads eagerly');
        foreach (['/images/home/hero-player.jpg', '/images/home/hero-player-mobile.jpg'] as $file) $this->assertFileExists(public_path(ltrim($file, '/')));
    }
    public function test_without_a_mobile_photo_the_desktop_photo_is_used_everywhere(): void
    {
        $this->heroData(['mobile_image' => '']);
        $hero = $this->hero();
        $this->assertStringNotContainsString('max-width: 767px', $hero);
        $this->assertStringContainsString('src="/images/home/hero-player.jpg"', $hero);
    }
    public function test_without_any_photo_the_hero_still_renders_its_text(): void
    {
        $this->heroData(['image' => '', 'mobile_image' => '']);
        $hero = $this->hero();
        $this->assertStringContainsString('<div class="kl-hero__image" aria-hidden="true"></div>', $hero);
        $this->assertStringNotContainsString('<img', $hero);
        $this->assertStringContainsString('THROUGH SPORT.', $hero);
        $this->assertStringContainsString('Book a Court', $hero);
    }

    // Other heroes and the CMS

    public function test_other_heroes_keep_the_standard_layout(): void
    {
        $about = $this->page('about', 'About');
        PageSection::create(['page_id' => $about->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => ['eyebrow' => 'ABOUT US', 'title' => 'DRIVEN BY|THE ELEMENTS', 'image' => '/images/home/hero-player.jpg', 'button_label' => 'Contact Us', 'button_url' => '/contact']]);
        $hero = $this->hero('/about');
        $this->assertStringContainsString('<section class="kl-hero">', $hero);
        $this->assertStringNotContainsString('kl-hero--cinematic', $hero);
        $this->assertStringNotContainsString('kl-hero__decor', $hero);
        $this->assertStringNotContainsString('kl-hero__highlight', $hero);
        $this->assertMatchesRegularExpression('#<span>DRIVEN BY\s*</span>\s*<span>THE ELEMENTS\s*</span>#', $hero);
        $this->assertStringContainsString('sizes="100vw"', $hero);
        $this->assertSame('standard', SectionDefaults::layout(PageSection::where('page_id', $about->id)->first()));
        // A home hero without a saved layout also keeps the standard look.
        $this->heroData(['layout' => '']);
        $this->assertStringContainsString('<section class="kl-hero kl-hero--panel">', $this->hero());
    }
    public function test_editors_choose_the_layout_and_unknown_layouts_are_rejected(): void
    {
        $this->actingAs(User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]));
        $draft = $this->page('camp', 'Camp', 'draft');
        $section = PageSection::create(['page_id' => $draft->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => ['title' => 'CAMP', 'image' => '/images/home/hero-player.jpg']]);
        $edit = $this->get(route('admin.pages.sections.edit', [$draft, $section]))->assertOk();
        $edit->assertSee('<option value="cinematic"', false)->assertSee('Full-width photo behind the text')->assertSee('Image beside the text')
            ->assertSee('Wrap words in asterisks to highlight them in the accent colour');
        $update = fn (array $data) => $this->from(route('admin.pages.sections.edit', [$draft, $section]))->put(route('admin.pages.sections.update', [$draft, $section]), $data + ['title' => 'CAMP', 'image_path' => '/images/home/hero-player.jpg', 'is_visible' => '1']);
        $update(['layout' => 'cinematic'])->assertSessionHasNoErrors();
        $this->assertSame('cinematic', $section->fresh()->data['layout']);
        $update(['layout' => 'carousel'])->assertSessionHasErrors('layout');
        $this->assertSame('cinematic', $section->fresh()->data['layout']);
        // The highlight hint is only for heroes.
        $faq = PageSection::create(['page_id' => $draft->id, 'type' => 'faq', 'sort_order' => 2, 'is_visible' => true, 'data' => []]);
        $this->get(route('admin.pages.sections.edit', [$draft, $faq]))->assertDontSee('Wrap words in asterisks');
    }
}

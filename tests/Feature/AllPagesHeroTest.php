<?php
namespace Tests\Feature;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
/**
 * The cinematic hero on every public page (one shared template; layout and overlay set per hero section). Each page
 * keeps its own content, photo and links; the data below mirrors the development site's heroes.
 */
class AllPagesHeroTest extends TestCase
{
    use RefreshDatabase;
    private const U = 'https://images.unsplash.com/';
    /** slug => hero data (as stored on the development site, with the cinematic layout). */
    private const HEROES = [
        'home' => ['title' => 'A STRONGER|*YOU*|THROUGH SPORT.', 'eyebrow' => 'DISCIPLINE  |  HEALTH  |  COMMUNITY', 'subtitle' => 'World-class badminton training, modern facilities and a vibrant sports community for all ages.', 'image' => '/images/home/hero-player.jpg', 'mobile_image' => '/images/home/hero-player-mobile.jpg', 'image_position' => '70% center', 'image_alt' => 'Badminton player in a green shirt leaping for an overhead shot on an indoor court', 'button_label' => 'Join Academy', 'button_url' => '/academy', 'secondary_button_label' => 'Book a Court', 'secondary_button_url' => ''],
        'academy' => ['title' => 'Build Skills.|Build Character.', 'eyebrow' => 'KELIKA ACADEMY', 'subtitle' => 'Coaching for every stage.', 'image' => self::U.'photo-1779608103749-395f76f42f94?auto=format&fit=crop&w=1800&q=85', 'image_position' => 'center', 'image_alt' => 'Adults and a child sharing a badminton session', 'button_label' => 'Explore Programs', 'button_url' => '/programs', 'secondary_button_label' => 'Contact Us', 'secondary_button_url' => '/contact'],
        'sports' => ['title' => 'Different Sports.|Stronger Individuals.', 'eyebrow' => 'OUR SPORTS', 'image' => self::U.'photo-1780233689566-55045608bd17?auto=format&fit=crop&w=1800&q=85', 'image_position' => 'center', 'image_alt' => 'Badminton players seen through a racket on an indoor court', 'button_label' => 'Explore Our Sports', 'button_url' => '#sports-list'],
        'programs' => ['title' => 'Structured Programs|for Every Stage', 'eyebrow' => 'PROGRAMS', 'image' => self::U.'photo-1774652995555-59fa40d9306c?auto=format&fit=crop&w=1800&q=85', 'image_position' => 'center', 'image_alt' => 'Athlete practicing badminton on an indoor court', 'button_label' => 'Explore Programs', 'button_url' => '#program-list', 'secondary_button_label' => 'Contact Us', 'secondary_button_url' => '/contact'],
        'facilities' => ['title' => 'World-class Facilities|for Every Athlete', 'eyebrow' => 'OUR FACILITIES', 'subtitle' => 'Train, play and grow in a modern, safe and inspiring environment.', 'image' => self::U.'photo-1775993167284-8e6a6e56ab69?auto=format&fit=crop&w=1800&q=85', 'image_position' => 'center', 'image_alt' => 'Indoor badminton sports hall with blue courts', 'button_label' => 'Explore Facilities', 'button_url' => '#facility-list', 'secondary_button_label' => 'Book a Court', 'secondary_button_url' => ''],
        'events' => ['title' => 'Tournaments, Camps|& Community Events', 'eyebrow' => 'EVENTS', 'image' => self::U.'photo-1783266503722-973f579d9b5a?auto=format&fit=crop&w=1800&q=85', 'image_position' => 'center', 'image_alt' => 'Players on a blue indoor badminton court', 'button_label' => 'Explore Events', 'button_url' => '#upcoming-events', 'secondary_button_label' => 'Contact Us', 'secondary_button_url' => '/contact'],
        'about' => ['title' => 'Driven by the Elements.|Built for a Brighter Tomorrow.', 'eyebrow' => 'ABOUT KELIKA', 'image' => self::U.'photo-1722087642932-9b070e9a066e?auto=format&fit=crop&w=1800&q=85', 'image_position' => 'center', 'image_alt' => 'Badminton player in action representing focus and determination', 'button_label' => 'Our Story', 'button_url' => '#our-story'],
        'contact' => ['title' => 'Let’s Build a|Stronger Sports Community.', 'eyebrow' => 'CONTACT US', 'image' => self::U.'photo-1775993167284-8e6a6e56ab69?auto=format&fit=crop&w=1800&q=85', 'image_position' => 'center', 'image_alt' => 'Indoor badminton court ready for players', 'button_label' => 'Send Us a Message', 'button_url' => '#contact-form', 'secondary_button_label' => 'Explore Sports', 'secondary_button_url' => '/sports'],
    ];
    /** @var array<string, PageSection> */
    private array $heroes = [];
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (self::HEROES as $slug => $data) {
            $page = Page::create(['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
            $this->heroes[$slug] = PageSection::create(['page_id' => $page->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => $data + ['layout' => 'cinematic']]);
        }
    }
    public static function innerPages(): array
    {
        return array_map(fn ($slug) => [$slug], array_combine($s = array_values(array_diff(array_keys(self::HEROES), ['home'])), $s));
    }
    private function hero(string $slug): string
    {
        $this->app['auth']->forgetGuards();
        $html = $this->get($slug === 'home' ? '/' : '/'.$slug)->assertOk()->getContent();
        $start = strpos($html, '<section class="kl-hero');
        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }
    private function link(string $hero, string $label, string $url): void
    {
        $this->assertMatchesRegularExpression('#<a class="btn btn-kelika(-outline)?"\s+href="'.preg_quote(e($url), '#').'"[^>]*>\s*'.preg_quote(e($label), '#').' <svg#', $hero, "{$label} → {$url}");
    }
    private ?User $editor = null;
    private function admin(): User
    {
        return $this->editor ??= User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
    }
    /** Saves the hero editor as a signed-in editor (hero() signs out to view pages as a visitor). */
    private function save(string $slug, array $fields)
    {
        $section = $this->heroes[$slug]->fresh();
        $edit = route('admin.pages.sections.edit', [$section->page, $section]);
        $response = $this->actingAs($this->admin())->from($edit)->put(route('admin.pages.sections.update', [$section->page, $section]), $fields + ['title' => $section->data['title'], 'is_visible' => '1']);
        $this->assertNotSame(route('login'), $response->headers->get('Location'), 'the save was made while signed in');
        // Saved: on to the page editor. Rejected: back to the section editor with errors.
        $this->assertContains($response->headers->get('Location'), [route('admin.pages.edit', $section->page), $edit]);
        return $response;
    }

    // Every page: its own content in the cinematic layout

    #[DataProvider('innerPages')]
    public function test_each_page_shows_its_own_content_and_photo_in_the_cinematic_layout(string $slug): void
    {
        $d = self::HEROES[$slug];
        $hero = $this->hero($slug);
        $this->assertStringContainsString('kl-hero--cinematic', $hero);
        foreach (explode('|', $d['title']) as $line) $this->assertMatchesRegularExpression('#<span>'.preg_quote(e($line), '#').'\s*</span>#', $hero);
        $this->assertStringContainsString('<p class="kl-kicker">'.e($d['eyebrow']).'</p>', $hero);
        if (isset($d['subtitle'])) $this->assertStringContainsString('<p class="kl-hero__lead">'.e($d['subtitle']).'</p>', $hero);
        $this->assertStringContainsString('src="'.e($d['image']).'"', $hero);
        $this->assertStringContainsString('alt="'.e($d['image_alt']).'"', $hero);
        $this->assertStringContainsString('style="--kl-hero-pos: center"', $hero);
        $this->assertStringContainsString('<div class="kl-hero__shade" aria-hidden="true"></div>', $hero, 'the default overlay');
        foreach (['A STRONGER', 'hero-player', 'Join Academy', 'DISCIPLINE'] as $homeOnly) $this->assertStringNotContainsString($homeOnly, $hero);
    }
    #[DataProvider('innerPages')]
    public function test_each_page_keeps_its_own_button_destinations(string $slug): void
    {
        $d = self::HEROES[$slug];
        $hero = $this->hero($slug);
        $this->link($hero, $d['button_label'], $d['button_url']);
        if (! empty($d['secondary_button_label'])) {
            $this->link($hero, $d['secondary_button_label'], $d['secondary_button_url'] ?: route('page.show', ['slug' => 'contact', 'topic' => 'Court Booking']).'#contact-form');
        } else {
            $this->assertSame(1, substr_count($hero, 'class="btn '), 'no second button is invented');
        }
    }
    #[DataProvider('innerPages')]
    public function test_each_page_hero_editor_has_the_layout_and_overlay_controls(string $slug): void
    {
        $this->actingAs($this->admin());
        $section = $this->heroes[$slug];
        $this->get(route('admin.pages.sections.edit', [$section->page, $section]))->assertOk()
            ->assertSee('<option value="cinematic" selected>Full-width photo behind the text</option>', false)
            ->assertSee('<option value="standard" >Image beside the text</option>', false)
            ->assertSee('name="overlay_darkness" min="0" max="80" step="1" value="53"', false)
            ->assertSee('data-range-value>53%</output>', false);
    }
    public function test_book_a_court_and_the_contact_page_buttons_keep_their_behaviour(): void
    {
        $this->link($this->hero('facilities'), 'Book a Court', route('page.show', ['slug' => 'contact', 'topic' => 'Court Booking']).'#contact-form');
        $contact = $this->hero('contact');
        $this->link($contact, 'Send Us a Message', '#contact-form');
        $this->link($contact, 'Explore Sports', '/sports');
        $this->assertStringNotContainsString('target="_blank"', $this->hero('facilities'), 'no booking URL is invented');
    }

    // Overlay: independent per hero, defaults, persistence, validation

    public function test_overlay_settings_are_independent_per_page(): void
    {
        $this->actingAs($this->admin());
        $this->save('academy', ['overlay_darkness' => '20'])->assertSessionHasNoErrors();
        $this->save('facilities', ['overlay_darkness' => '70'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('style="--kl-shade: 0.3774" data-overlay="20"', $this->hero('academy'));
        $this->assertStringContainsString('style="--kl-shade: 1.3208" data-overlay="70"', $this->hero('facilities'));
        foreach (['home', 'sports', 'programs', 'events', 'about', 'contact'] as $other) {
            $this->assertStringContainsString('<div class="kl-hero__shade" aria-hidden="true"></div>', $this->hero($other), $other);
            $this->assertArrayNotHasKey('overlay_darkness', $this->heroes[$other]->fresh()->data, $other);
        }
    }
    public function test_an_inner_page_overlay_persists_validates_and_falls_back(): void
    {
        $this->actingAs($this->admin());
        $section = $this->heroes['about'];
        $this->save('about', ['overlay_darkness' => '35'])->assertSessionHasNoErrors();
        $this->assertSame(35, $section->fresh()->data['overlay_darkness']);
        $this->get(route('admin.pages.sections.edit', [$section->page, $section]))->assertSee('value="35" data-range-output', false)->assertSee('data-range-value>35%</output>', false);
        foreach (['81', '-1', 'dark', '12.5'] as $bad) $this->save('about', ['overlay_darkness' => $bad])->assertSessionHasErrors('overlay_darkness');
        $this->assertSame(35, $section->fresh()->data['overlay_darkness']);
        // A value stored outside the range (older or hand-edited data) shows the default overlay.
        $section->update(['data' => ['overlay_darkness' => 500] + $section->fresh()->data]);
        $this->assertStringContainsString('<div class="kl-hero__shade" aria-hidden="true"></div>', $this->hero('about'));
    }

    // Layout choice, responsive behaviour, fallbacks

    public function test_the_standard_layout_remains_available_per_page(): void
    {
        $this->actingAs($this->admin());
        $this->save('contact', ['layout' => 'standard'])->assertSessionHasNoErrors();
        $contact = $this->hero('contact');
        $this->assertStringStartsWith('<section class="kl-hero">', $contact);
        $this->assertStringNotContainsString('kl-hero__shade', $contact);
        $this->link($contact, 'Send Us a Message', '#contact-form');
        $this->assertStringContainsString('kl-hero--cinematic', $this->hero('academy'), 'other pages keep their own layout');
        $this->save('contact', ['layout' => 'cinematic'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('kl-hero--cinematic', $this->hero('contact'));
    }
    public function test_responsive_classes_follow_each_pages_content(): void
    {
        // Longest title line over 16 characters: smaller headline. Academy's longest line is exactly 16.
        foreach (['sports', 'programs', 'facilities', 'events', 'about', 'contact'] as $long) $this->assertStringContainsString('kl-hero--long-title', $this->hero($long), $long);
        $this->assertStringNotContainsString('kl-hero--long-title', $this->hero('academy'));
        // Pages without a separate phone image get a landscape phone frame; the homepage has a phone image.
        $this->assertStringContainsString('kl-hero--no-mobile-image', $this->hero('events'));
        $this->assertStringNotContainsString('max-width: 767px', $this->hero('events'), 'the page photo is used on phones too');
        $home = $this->hero('home');
        $this->assertStringStartsWith('<section class="kl-hero kl-hero--cinematic">', $home, 'the homepage hero is unchanged');
        $this->assertStringContainsString('media="(max-width: 767px)"', $home);
        // The page photo keeps its responsive widths, sized for the cinematic layout.
        $academy = $this->hero('academy');
        $this->assertStringContainsString('w=800&amp;q=85 800w', $academy);
        $this->assertStringContainsString('sizes="(min-width: 1200px) 64vw, (min-width: 992px) 70vw, 100vw"', $academy);
    }
    public function test_highlight_markers_count_as_characters_only_once_removed(): void
    {
        $this->heroes['academy']->update(['data' => ['title' => 'Build Skills.|*Build Character.*'] + $this->heroes['academy']->data]);
        $this->assertStringNotContainsString('kl-hero--long-title', $this->hero('academy'), 'the asterisks do not count towards the length');
    }
    public function test_a_page_without_a_photo_still_renders_its_hero_safely(): void
    {
        $this->heroes['events']->update(['data' => ['image' => ''] + $this->heroes['events']->data]);
        $hero = $this->hero('events');
        $this->assertStringContainsString('<div class="kl-hero__image" aria-hidden="true"></div>', $hero);
        $this->assertStringNotContainsString('kl-hero--no-mobile-image', $hero);
        $this->assertMatchesRegularExpression('#<span>Tournaments, Camps\s*</span>#', $hero);
        $this->link($hero, 'Explore Events', '#upcoming-events');
    }
    public function test_the_rest_of_each_page_is_not_affected(): void
    {
        $page = $this->heroes['facilities']->page;
        PageSection::create(['page_id' => $page->id, 'type' => 'rich_text', 'sort_order' => 2, 'is_visible' => true, 'data' => ['title' => 'Court details', 'body' => 'Six courts.']]);
        $this->app['auth']->forgetGuards();
        $html = $this->get('/facilities')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'kl-hero--cinematic'));
        $this->assertStringContainsString('Six courts.', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'one main heading per page');
    }
}

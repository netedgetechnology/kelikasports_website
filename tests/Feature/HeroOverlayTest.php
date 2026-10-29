<?php
namespace Tests\Feature;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use App\Support\SectionDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/** The "Image Overlay Darkness" control of the cinematic hero (0–80 %, default 53 % = the original design). */
class HeroOverlayTest extends TestCase
{
    use RefreshDatabase;
    private const DEFAULT_SHADE = '<div class="kl-hero__shade" aria-hidden="true"></div>';
    private Page $home;
    private PageSection $hero;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->home = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
        $this->hero = PageSection::create(['page_id' => $this->home->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => [
            'layout' => 'cinematic', 'title' => 'A STRONGER|*YOU*|THROUGH SPORT.', 'image' => '/images/home/hero-player.jpg', 'mobile_image' => '/images/home/hero-player-mobile.jpg', 'image_position' => '70% center',
        ]]);
    }
    private function shade(): ?string
    {
        $this->app['auth']->forgetGuards();
        preg_match('#<div class="kl-hero__shade[^"]*"[^>]*></div>#', $this->get('/')->assertOk()->getContent(), $m);
        return $m[0] ?? null;
    }
    private function store(mixed $overlay): void
    {
        $data = $this->hero->data;
        if ($overlay === null) unset($data['overlay_darkness']); else $data['overlay_darkness'] = $overlay;
        $this->hero->update(['data' => $data]);
    }
    private function editor(): User
    {
        return User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
    }
    /** A hero on a draft page, so saving the editor is not subject to the published-hero rules. */
    private function draftHero(array $data = []): PageSection
    {
        $draft = Page::create(['title' => 'Camp', 'slug' => 'camp', 'status' => 'draft', 'robots_directive' => 'index,follow', 'template' => 'standard']);
        return PageSection::create(['page_id' => $draft->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => $data + ['layout' => 'cinematic', 'title' => 'CAMP']]);
    }
    private function save(PageSection $section, array $fields)
    {
        $page = $section->page;
        return $this->from(route('admin.pages.sections.edit', [$page, $section]))->put(route('admin.pages.sections.update', [$page, $section]), $fields + ['title' => $section->data['title'] ?? 'CAMP', 'is_visible' => '1']);
    }

    // Default

    public function test_the_default_is_53_and_renders_the_original_overlay_unchanged(): void
    {
        $this->assertSame(53, SectionDefaults::OVERLAY_DEFAULT);
        $this->assertSame(53, SectionDefaults::overlayDarkness($this->hero));
        $this->assertSame(self::DEFAULT_SHADE, $this->shade(), 'no saved value: the original markup');
        $this->store(53);
        $this->assertSame(self::DEFAULT_SHADE, $this->shade(), 'the default saved explicitly: the same markup');
    }
    public function test_the_default_overlay_css_is_untouched_and_the_custom_rule_scales_the_same_stops(): void
    {
        $css = file_get_contents(resource_path('css/home.css'));
        // The original rules, exactly as designed (phones/tablets, then desktop).
        $phone = 'linear-gradient(180deg, rgba(0, 0, 0, .12) 0%, rgba(0, 0, 0, 0) 45%, rgba(0, 0, 0, .78) 88%, var(--kl-cine-ink) 100%)';
        $desktop = 'linear-gradient(90deg, var(--kl-cine-ink) 0%, rgba(0, 0, 0, .9) 12%, rgba(0, 0, 0, .5) 38%, rgba(0, 0, 0, .12) 68%, rgba(0, 0, 0, .3) 100%), linear-gradient(0deg, rgba(0, 0, 0, .82) 0%, rgba(0, 0, 0, 0) 34%)';
        // The fixed edge blend (the photo's fade into the text area), set per breakpoint and layered on top of the tint.
        $phoneBlend = 'linear-gradient(0deg, #000000 0%, #000000 3%, rgba(0, 0, 0, .98) 7%, rgba(0, 0, 0, .923) 11%, rgba(0, 0, 0, .838) 15%, rgba(0, 0, 0, .731) 19%, rgba(0, 0, 0, .611) 23%, rgba(0, 0, 0, .484) 27%, rgba(0, 0, 0, .358) 31%, rgba(0, 0, 0, .241) 35%, rgba(0, 0, 0, .139) 39%, rgba(0, 0, 0, .06) 43%, rgba(0, 0, 0, .012) 47%, rgba(0, 0, 0, 0) 50%)';
        $desktopBlend = 'linear-gradient(90deg, #000000 0%, #000000 4%, rgba(0, 0, 0, .998) 8%, rgba(0, 0, 0, .984) 12%, rgba(0, 0, 0, .951) 16%, rgba(0, 0, 0, .896) 20%, rgba(0, 0, 0, .82) 24%, rgba(0, 0, 0, .725) 28%, rgba(0, 0, 0, .616) 32%, rgba(0, 0, 0, .5) 36%, rgba(0, 0, 0, .384) 40%, rgba(0, 0, 0, .275) 44%, rgba(0, 0, 0, .18) 48%, rgba(0, 0, 0, .104) 52%, rgba(0, 0, 0, .049) 56%, rgba(0, 0, 0, .016) 60%, rgba(0, 0, 0, .002) 64%, rgba(0, 0, 0, 0) 68%)';
        // The custom rules: same colours, positions and stops; each opacity times --kl-shade (the ink colour is opacity 1).
        $scale = fn (string $gradient) => preg_replace(['/var\(--kl-cine-ink\)/', '/rgba\((\d+, \d+, \d+), (\.\d+|1)\)/'], ['rgba(0, 0, 0, 1)', 'rgba($1, calc($2 * var(--kl-shade, 1)))'], $gradient);
        $fallback = file_get_contents(public_path('css/kelika-home.css'));
        foreach (['home.css' => $css, 'kelika-home.css' => $fallback] as $name => $sheet) {
            $this->assertStringContainsString('.kl-hero.kl-hero--cinematic .kl-hero__shade { --kl-hero-blend: '.$phoneBlend.'; position: absolute; inset: 0; z-index: 1; pointer-events: none; background: var(--kl-hero-blend), '.$phone.'; }', $sheet, $name);
            $this->assertStringContainsString('.kl-hero.kl-hero--cinematic .kl-hero__shade { --kl-hero-blend: '.$desktopBlend.'; background: var(--kl-hero-blend), '.$desktop.'; }', $sheet, $name);
            // The blend is never scaled by the setting, so the photo's edge stays solid black even at 0 %.
            $this->assertStringContainsString('.kl-hero__shade.kl-hero__shade--custom { background: var(--kl-hero-blend), '.$scale($phone).'; }', $sheet, $name);
            $this->assertStringContainsString('.kl-hero__shade.kl-hero__shade--custom { background: var(--kl-hero-blend), '.$scale($desktop).'; }', $sheet, $name);
        }
    }
    public function test_the_edge_blend_starts_solid_black_and_eases_out_smoothly(): void
    {
        $css = file_get_contents(resource_path('css/home.css'));
        preg_match_all('/--kl-hero-blend: linear-gradient\((0|90)deg, ([^;]+)\);/', $css, $m, PREG_SET_ORDER);
        $this->assertCount(2, $m, 'one blend for phones/tablets (bottom edge), one from 992px (left edge)');
        foreach ($m as [, $angle, $stops]) {
            preg_match_all('/(?:#000000|rgba\(0, 0, 0, ([\d.]+)\)) (\d+)%/', $stops, $s, PREG_SET_ORDER);
            $points = array_map(fn ($stop) => [(int) $stop[2], $stop[1] === '' ? 1.0 : (float) $stop[1]], $s);
            $this->assertSame([0, 1.0], $points[0], "{$angle}deg: solid black where the photo meets the text");
            // Phones/tablets: clear before the middle of the photo. From 992px the fade spans more of the photo's width so the
            // black reaches over its left edge, but the right third of the photo is always untouched.
            $this->assertSame(0.0, end($points)[1], "{$angle}deg: fully clear well before the far edge of the photo");
            $this->assertLessThanOrEqual($angle === '0' ? 50 : 70, end($points)[0], "{$angle}deg");
            // A short solid-black hold may come first; from there the fade only ever eases out.
            $hold = 1;
            while ($points[$hold][1] === 1.0) $hold++;
            $this->assertLessThanOrEqual(5, $points[$hold - 1][0], "{$angle}deg: the solid hold stays at the very edge");
            foreach (array_slice($points, $hold, null, true) as $i => [$at, $alpha]) {
                [$prevAt, $prevAlpha] = $points[$i - 1];
                $this->assertLessThan($prevAlpha, $alpha, "{$angle}deg: fades out steadily");
                $this->assertLessThanOrEqual(.035, ($prevAlpha - $alpha) / ($at - $prevAt), "{$angle}deg: no abrupt step at {$at}%");
            }
            // An S-curve, not a linear ramp: the first and last fading steps are far gentler than the steepest one, so neither
            // where the photo starts to appear nor where it is fully clear reads as an edge.
            $slopes = [];
            foreach (array_slice($points, $hold, null, true) as $i => [$at, $alpha]) $slopes[] = ($points[$i - 1][1] - $alpha) / ($at - $points[$i - 1][0]);
            $this->assertLessThan(max($slopes) / 4, $slopes[0], "{$angle}deg: eases in");
            $this->assertLessThan(max($slopes) / 4, end($slopes), "{$angle}deg: eases out");
        }
    }
    public function test_the_cinematic_hero_is_tinted_pure_black_not_green(): void
    {
        foreach (['home.css' => resource_path('css/home.css'), 'kelika-home.css' => public_path('css/kelika-home.css')] as $name => $path) {
            $css = file_get_contents($path);
            $this->assertStringContainsString('.kl-hero.kl-hero--cinematic { --kl-cine-ink: #000000; --kl-cine-deep: #000000;', $css, $name);
            // The former forest-green ink (#0b2925) and deep green (#061a17), in hex or rgba form, are gone from the hero.
            foreach (['#0b2925', '#061a17', 'rgba(11, 41, 37,', 'rgba(6, 26, 23,'] as $green) {
                $this->assertStringNotContainsString($green, $css, "{$name}: {$green}");
            }
        }
    }
    public function test_the_built_stylesheet_is_tinted_pure_black_not_green(): void
    {
        $manifest = public_path('build/manifest.json');
        if (! is_file($manifest)) $this->markTestSkipped('No Vite build; run npm run build.');
        $css = file_get_contents(public_path('build/'.json_decode(file_get_contents($manifest), true)['resources/css/app.css']['file']));
        $this->assertStringContainsString('.kl-hero.kl-hero--cinematic{--kl-cine-ink: #000000;--kl-cine-deep: #000000;', $css);
        // The minifier may write the former greens as hex (#0b2925, with alpha #0b2925c7…) or compact rgba().
        foreach (['#0b2925', '#061a17', 'rgba(11,41,37,', 'rgba(6,26,23,', 'rgba(11, 41, 37,', 'rgba(6, 26, 23,'] as $green) {
            $this->assertStringNotContainsString($green, $css, $green);
        }
        // The built file carries the edge blend for both breakpoints, and both custom-overlay rules keep it on top.
        foreach (['0deg', '90deg'] as $angle) {
            $this->assertStringContainsString('.kl-hero.kl-hero--cinematic .kl-hero__shade{--kl-hero-blend: linear-gradient('.$angle.', #000000 0%', $css, $angle);
        }
        $this->assertSame(2, substr_count($css, '.kl-hero.kl-hero--cinematic .kl-hero__shade.kl-hero__shade--custom{background:var(--kl-hero-blend),'));
    }
    public function test_every_public_page_hero_gets_the_black_cinematic_treatment(): void
    {
        $slugs = ['academy', 'sports', 'programs', 'facilities', 'events', 'about', 'contact'];
        foreach ($slugs as $i => $slug) {
            $page = Page::create(['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
            // Alternate the default overlay and a custom value, so both shade rules are exercised on inner pages.
            PageSection::create(['page_id' => $page->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => ['layout' => 'cinematic', 'title' => strtoupper($slug), 'image' => '/images/home/hero-player.jpg'] + ($i % 2 ? ['overlay_darkness' => 80] : [])]);
        }
        $this->store(64);
        foreach (['/' => 64] + array_fill_keys(array_map(fn ($s) => "/{$s}", $slugs), null) as $path => $overlay) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#<section class="kl-hero[^"]*\bkl-hero--cinematic\b#', $html, $path);
            $this->assertMatchesRegularExpression('#<div class="kl-hero__shade( kl-hero__shade--custom)?" aria-hidden="true"#', $html, $path);
            $this->assertDoesNotMatchRegularExpression('#\b(0b2925|061a17)\b|rgba\((11, ?41, ?37|6, ?26, ?23)#', $html, "{$path}: no inline green tint");
        }
        $this->assertStringContainsString('data-overlay="64"', $this->get('/')->getContent());
        $this->assertStringContainsString('data-overlay="80"', $this->get('/sports')->getContent());
        $this->assertStringContainsString(self::DEFAULT_SHADE, $this->get('/academy')->getContent(), 'no saved value: the default shade');
    }
    public function test_missing_or_invalid_values_fall_back_to_the_default(): void
    {
        foreach ([null, '', 'abc', -5, 81, 95, '12.5', 12.5, [30], true, '53abc'] as $bad) {
            $this->store($bad);
            $this->assertSame(53, SectionDefaults::overlayDarkness($this->hero->fresh()), json_encode($bad));
            $this->assertSame(self::DEFAULT_SHADE, $this->shade(), json_encode($bad));
        }
    }

    // Rendering at different values

    public function test_other_values_scale_the_overlay_proportionally(): void
    {
        foreach ([0 => '0', 26 => '0.4906', 40 => '0.7547', 80 => '1.5094'] as $value => $factor) {
            $this->store($value);
            $this->assertSame('<div class="kl-hero__shade kl-hero__shade--custom" aria-hidden="true" style="--kl-shade: '.$factor.'" data-overlay="'.$value.'"></div>', $this->shade(), "{$value}%");
        }
        $this->store('30'); // a numeric string from older data is read as a number
        $this->assertStringContainsString('data-overlay="30"', $this->shade());
    }
    public function test_the_setting_never_affects_the_standard_hero_layout_or_other_pages(): void
    {
        $this->store(10);
        $about = Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
        PageSection::create(['page_id' => $about->id, 'type' => 'hero', 'sort_order' => 1, 'is_visible' => true, 'data' => ['title' => 'ABOUT', 'image' => '/images/home/hero-player.jpg', 'overlay_darkness' => 70]]);
        $html = $this->get('/about')->assertOk()->getContent();
        $this->assertStringNotContainsString('kl-hero__shade', $html, 'the standard layout has no overlay to adjust');
        $this->assertStringNotContainsString('--kl-shade', $html);
        $this->store(null);
        $this->hero->update(['data' => array_replace($this->hero->data, ['layout' => 'standard', 'overlay_darkness' => 10])]);
        $this->assertNull($this->shade());
    }

    // Editor: display, persistence and validation

    public function test_the_hero_editor_shows_the_slider_with_its_value(): void
    {
        $this->actingAs($this->editor());
        $html = $this->get(route('admin.pages.sections.edit', [$this->home, $this->hero]))->assertOk()->getContent();
        $this->assertStringContainsString('<label class="form-label" for="overlay_darkness">Image Overlay Darkness: <output id="overlay_darkness_value" for="overlay_darkness" data-range-value>53%</output></label>', $html);
        $this->assertStringContainsString('<input class="form-range" type="range" id="overlay_darkness" name="overlay_darkness" min="0" max="80" step="1" value="53" data-range-output="overlay_darkness_value"', $html);
        $this->assertStringContainsString('The black shade over the photo', $html);
        $this->assertStringContainsString('Default 53% (the original design); 0% removes it, leaving only the soft fade where the photo meets the text.', $html);
        $faq = PageSection::create(['page_id' => $this->home->id, 'type' => 'faq', 'sort_order' => 2, 'is_visible' => true, 'data' => []]);
        $this->get(route('admin.pages.sections.edit', [$this->home, $faq]))->assertOk()->assertDontSee('overlay_darkness', false);
    }
    public function test_a_saved_value_persists_and_shows_when_the_editor_is_reopened(): void
    {
        $this->actingAs($this->editor());
        $section = $this->draftHero();
        $this->save($section, ['overlay_darkness' => '30'])->assertSessionHasNoErrors();
        $this->assertSame(30, $section->fresh()->data['overlay_darkness']);
        $this->get(route('admin.pages.sections.edit', [$section->page, $section]))->assertSee('value="30" data-range-output', false)->assertSee('data-range-value>30%</output>', false);
        $this->save($section->fresh(), ['overlay_darkness' => '80'])->assertSessionHasNoErrors();
        $this->assertSame(80, $section->fresh()->data['overlay_darkness']);
        $this->save($section->fresh(), ['overlay_darkness' => '53'])->assertSessionHasNoErrors();
        $this->assertSame(53, $section->fresh()->data['overlay_darkness'], 'returning to the default is kept as chosen');
        $this->save($section->fresh(), ['overlay_darkness' => ''])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('overlay_darkness', $section->fresh()->data, 'empty restores the default');
        $this->save($section->fresh(), ['overlay_darkness' => '0'])->assertSessionHasNoErrors();
        $this->assertSame(0, $section->fresh()->data['overlay_darkness']);
    }
    public function test_saving_an_untouched_slider_leaves_the_section_data_unchanged(): void
    {
        $this->actingAs($this->editor());
        $section = $this->draftHero();
        $before = $section->data;
        $this->save($section, ['overlay_darkness' => '53'])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('overlay_darkness', $section->fresh()->data);
        $this->assertSame($before['layout'], $section->fresh()->data['layout']);
        $this->save($section->fresh(), [])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('overlay_darkness', $section->fresh()->data, 'a form without the field leaves it alone');
    }
    public function test_out_of_range_and_non_numeric_values_are_rejected(): void
    {
        $this->actingAs($this->editor());
        $section = $this->draftHero(['overlay_darkness' => 40]);
        foreach (['81', '100', '-1', 'abc', '12.5', '1e2', '40px'] as $bad) {
            $this->save($section, ['overlay_darkness' => $bad])->assertSessionHasErrors('overlay_darkness');
            $this->assertSame(40, $section->fresh()->data['overlay_darkness'], $bad);
        }
        $faq = PageSection::create(['page_id' => $section->page_id, 'type' => 'faq', 'sort_order' => 2, 'is_visible' => true, 'data' => ['title' => 'FAQ']]);
        $this->save($faq, ['overlay_darkness' => '30'])->assertSessionHasErrors('overlay_darkness');
        $this->assertArrayNotHasKey('overlay_darkness', $faq->fresh()->data);
    }
}

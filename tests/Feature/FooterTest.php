<?php
namespace Tests\Feature;
use App\Models\ContentItem;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\PublicSiteData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
/** The site footer (design-reference/KelikaSportsFooter.png). The call to action that sat above it has been removed. */
class FooterTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (['home', 'about', 'contact', 'academy', 'sports'] as $slug) $this->page($slug);
        Setting::write('site_name', 'KELIKA');
        Setting::write('site_tagline', 'Driven by the elements. Built for stronger athletes and a brighter tomorrow.');
        Setting::write('contact_phone', '7874028876', 'contact');
        $footer = Menu::create(['name' => 'Footer', 'location' => 'footer', 'is_active' => true]);
        foreach (['Home' => '/', 'About' => '/about', 'Contact' => '/contact'] as $i => $url) $footer->menuItems()->create(['label' => $i, 'url' => $url, 'target' => '_self', 'sort_order' => count($footer->menuItems()->get()) + 1, 'is_visible' => true]);
        foreach (['Badminton', 'Football'] as $i => $sport) ContentItem::create(['type' => 'sport', 'title' => $sport, 'slug' => strtolower($sport), 'status' => 'published', 'sort_order' => $i, 'data' => []]);
    }
    private function page(string $slug, string $status = 'published'): Page
    {
        return Page::create(['title' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'status' => $status, 'published_at' => $status === 'published' ? now()->subDay() : null, 'robots_directive' => 'index,follow', 'template' => 'standard']);
    }
    private function part(string $html, string $open, string $close): string
    {
        $start = strpos($html, $open);
        $this->assertNotFalse($start, "{$open} rendered");
        return substr($html, $start, strpos($html, $close, $start) - $start + strlen($close));
    }
    private function footer(string $uri = '/about'): string
    {
        $this->app['auth']->forgetGuards();
        return $this->part($this->get($uri)->assertOk()->getContent(), '<footer class="site-footer kl-footer">', '</footer>');
    }
    // Call to action (removed)

    public function test_no_site_wide_call_to_action_is_shown_above_the_footer(): void
    {
        foreach (['/', '/about', '/contact', '/search?q=x'] as $uri) {
            $html = $this->get($uri)->assertOk()->getContent();
            $this->assertStringNotContainsString('kl-footer-cta', $html, $uri);
            $this->assertStringNotContainsString('Ready to <span', $html, $uri);
            // The footer is still there, right after the page content.
            $this->assertMatchesRegularExpression('#</main>\s*<footer class="site-footer kl-footer">#', $html, "{$uri}: footer directly after the content");
            $this->assertStringContainsString('<div class="kl-footer__columns">', $html, $uri);
            $this->assertStringContainsString('© '.now()->year.' KELIKA. All rights reserved.', $html, $uri);
        }
        $missing = $this->get('/no-such-page')->assertNotFound()->getContent();
        $this->assertStringNotContainsString('kl-footer-cta', $missing);
        $this->assertStringContainsString('<footer class="site-footer kl-footer">', $missing);
    }

    // Brand column and social links

    public function test_the_logo_tagline_and_white_footer_are_kept(): void
    {
        Setting::write('logo_url', '/images/kelika-wordmark.png', 'branding');
        $footer = $this->footer();
        $this->assertStringContainsString('<footer class="site-footer kl-footer">', $footer);
        $this->assertMatchesRegularExpression('#<a class="kl-footer__brand" href="[^"]+">.*kelika-wordmark.*alt="KELIKA"#s', $footer);
        $this->assertStringContainsString('<p class="kl-footer__tagline">Driven by the elements. Built for stronger athletes and a brighter tomorrow.</p>', $footer);
        $this->assertStringNotContainsString('kl-decor--leaves', $footer);
    }
    public function test_without_configured_profiles_no_social_links_are_shown(): void
    {
        $footer = $this->footer();
        $this->assertStringNotContainsString('kl-footer__social', $footer);
        foreach (['facebook.com', 'instagram.com', 'youtube.com', 'linkedin.com', 'x.com', 'twitter.com', 'href="#"'] as $invented) $this->assertStringNotContainsString($invented, $footer);
    }
    public function test_configured_profiles_are_linked_in_order_with_brand_icons(): void
    {
        $profiles = ['x_url' => 'https://x.com/example', 'instagram_url' => 'https://www.instagram.com/example', 'facebook_url' => 'https://www.facebook.com/example'];
        foreach ($profiles as $key => $url) Setting::write($key, $url, 'social');
        $footer = $this->footer();
        $this->assertStringContainsString('<ul class="kl-footer__social" aria-label="KELIKA on social media">', $footer);
        preg_match_all('#<a class="kl-footer__social-link kl-footer__social-link--([a-z]+)" href="([^"]+)" target="_blank" rel="noopener noreferrer" aria-label="KELIKA on ([^"]+)"><svg#', $footer, $m);
        $this->assertSame(['facebook', 'instagram', 'x'], $m[1], 'the reference order, only configured networks');
        $this->assertSame([$profiles['facebook_url'], $profiles['instagram_url'], $profiles['x_url']], $m[2]);
        $this->assertSame(['Facebook', 'Instagram', 'X'], $m[3]);
        $this->assertStringNotContainsString('youtube', $footer);
    }
    public function test_a_stored_profile_that_is_not_a_web_address_is_never_linked(): void
    {
        Setting::write('facebook_url', 'javascript:alert(1)', 'social');
        Setting::write('youtube_url', '//youtube.example/kelika', 'social');
        $footer = $this->footer();
        $this->assertStringNotContainsString('javascript:', $footer);
        $this->assertStringNotContainsString('youtube.example', $footer);
    }
    public function test_the_x_profile_can_be_set_and_profiles_must_be_web_addresses(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        $this->get(route('admin.settings.edit'))->assertSee('<label class="form-label" for="x_url">X (Twitter) URL</label>', false);
        $base = array_fill_keys(['primary_color', 'terracotta_color', 'gold_color', 'earth_color', 'water_color', 'fire_color', 'air_color', 'space_color'], '#123abc') + ['site_name' => 'KELIKA', 'booking_button_label' => 'Book a Court', 'font_family' => 'Inter', 'button_style' => 'square'];
        $this->put(route('admin.settings.update'), ['x_url' => 'https://x.com/example'] + $base)->assertSessionHasNoErrors();
        $this->assertSame(['https://x.com/example', 'social'], [Setting::read('x_url'), Setting::where('key', 'x_url')->value('group')]);
        foreach (['x_url', 'facebook_url', 'instagram_url', 'youtube_url', 'linkedin_url'] as $field) {
            foreach (['ftp://example.test/profile', 'javascript:alert(1)', 'not a url'] as $bad) $this->put(route('admin.settings.update'), [$field => $bad] + $base)->assertSessionHasErrors($field);
        }
        $this->assertSame('https://x.com/example', Setting::read('x_url'));
    }

    // Navigation and contact

    public function test_navigation_columns_keep_the_existing_links(): void
    {
        $footer = $this->footer();
        $this->assertMatchesRegularExpression('#<h2 class="footer-heading">Quick Links</h2>.*?href="/"[^>]*>\s*Home\s*</a>.*?href="/about"[^>]*>\s*About\s*</a>.*?href="/contact"[^>]*>\s*Contact\s*</a>#s', $footer);
        $this->assertMatchesRegularExpression('#<h2 class="footer-heading">Our Sports</h2>.*?>Badminton</a>.*?>Football</a>.*?href="'.preg_quote(route('page.show', ['slug' => 'sports']), '#').'">More Sports</a>#s', $footer);
        // Left: brand; middle: Quick Links and Our Sports; right: Contact (a CSS grid, in this order).
        preg_match_all('#<div class="(kl-footer__about|kl-footer__col kl-footer__col--[a-z]+)">#', $footer, $columns);
        $this->assertSame(['kl-footer__about', 'kl-footer__col kl-footer__col--links', 'kl-footer__col kl-footer__col--sports', 'kl-footer__col kl-footer__col--contact'], $columns[1]);
        $this->assertStringContainsString('<div class="kl-footer__columns">', $footer);
    }
    public function test_contact_shows_the_phone_and_only_a_configured_address(): void
    {
        $footer = $this->footer();
        $this->assertMatchesRegularExpression('#<span class="kl-footer__contact-icon"><svg[^>]*>.*?</svg></span><a href="tel:7874028876">7874028876</a>#', $footer);
        $this->assertStringNotContainsString('Ahmedabad', $footer, 'the reference address is never invented');
        $this->assertStringNotContainsString('mailto:', $footer);
        $this->assertMatchesRegularExpression('#<a class="kl-link-arrow kl-footer__contact-link" href="'.preg_quote(route('page.show', ['slug' => 'contact']), '#').'"><span class="kl-footer__contact-label">Contact us</span> <svg#', $footer, 'the gold underline is on the words only');

        Setting::write('contact_address', "Unit 1, Test Road\nTest City <b>", 'contact');
        Setting::write('contact_email', 'hello@example.test', 'contact');
        $footer = $this->footer();
        $this->assertStringContainsString('<span>Unit 1, Test Road<br />'."\n".'Test City &lt;b&gt;</span>', $footer);
        $this->assertStringContainsString('<a href="mailto:hello@example.test">hello@example.test</a>', $footer);
    }

    // Bottom bar and legal pages

    public function test_the_bottom_bar_keeps_the_copyright_and_links_legal_pages_only_when_they_exist(): void
    {
        $footer = $this->footer();
        $this->assertStringContainsString('<span>© '.now()->year.' KELIKA. All rights reserved.</span>', $footer);
        $this->assertStringContainsString('<li>Play. Grow. Belong.</li>', $footer);
        $this->assertStringNotContainsString('Terms of Service', $footer, 'no legal page yet: no link');
        $this->assertStringNotContainsString('Privacy Policy', $footer);

        $this->page('privacy-policy');
        $this->page('terms-of-service', 'draft');
        $footer = $this->footer();
        $this->assertStringContainsString('<li><a href="'.route('page.show', ['slug' => 'privacy-policy']).'">Privacy Policy</a></li>', $footer);
        $this->assertStringNotContainsString('Terms of Service', $footer, 'a draft page is not linked');

        $this->page('terms'); // a shorter slug works too
        $this->assertStringContainsString('<li><a href="'.route('page.show', ['slug' => 'terms']).'">Terms of Service</a></li>', $this->footer());
        $this->get(route('page.show', ['slug' => 'terms']))->assertOk();
    }
    public function test_publishing_a_legal_page_updates_a_cached_footer(): void
    {
        $this->footer();
        $this->assertTrue(Cache::has(PublicSiteData::CACHE_KEY));
        $terms = $this->page('terms-of-service', 'draft');
        $terms->update(['status' => 'published', 'published_at' => now()->subMinute()]);
        $this->assertStringContainsString('Terms of Service</a>', $this->footer());
        $terms->delete();
        $this->assertStringNotContainsString('Terms of Service', $this->footer(), 'an archived page is unlinked again');
    }
    public function test_cached_legal_links_never_carry_another_requests_host(): void
    {
        $this->page('privacy-policy');
        $this->get('http://attacker.example/about')->assertOk(); // fills the shared cache
        $this->assertTrue(Cache::has(PublicSiteData::CACHE_KEY));
        $this->assertStringNotContainsString('attacker.example', json_encode(Cache::get(PublicSiteData::CACHE_KEY)['footerLegal']));
        // The next visitor, on the site's own address, gets links on that address.
        $footer = $this->footer(rtrim(config('app.url'), '/').'/about');
        $this->assertStringNotContainsString('attacker.example', $footer);
        $this->assertStringContainsString('href="'.rtrim(config('app.url'), '/').'/privacy-policy">Privacy Policy', $footer);
    }

    public function test_legal_pages_can_be_chosen_in_global_settings(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]);
        $base = array_fill_keys(['primary_color', 'terracotta_color', 'gold_color', 'earth_color', 'water_color', 'fire_color', 'air_color', 'space_color'], '#123abc') + ['site_name' => 'KELIKA', 'booking_button_label' => 'Book a Court', 'font_family' => 'Inter', 'button_style' => 'square'];
        $legal = $this->page('legal-notice');
        $draft = $this->page('policy-draft', 'draft');
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('<h2 class="h5 fw-bold" id="footer-legal">Footer legal links</h2>', false)
            ->assertSee('<option value="">Automatic: the page at /terms-of-service, if published</option>', false)
            ->assertSee('<option value="policy-draft" >Policy Draft (/policy-draft, not published)</option>', false);
        $this->put(route('admin.settings.update'), ['terms_page' => 'legal-notice', 'privacy_page' => 'policy-draft'] + $base)->assertSessionHasNoErrors();
        $this->assertSame(['legal-notice', 'general'], [Setting::read('terms_page'), Setting::where('key', 'terms_page')->value('group')]);
        $footer = $this->footer();
        $this->assertStringContainsString('<li><a href="'.route('page.show', ['slug' => 'legal-notice']).'">Terms of Service</a></li>', $footer, 'the chosen page wins');
        $this->assertStringNotContainsString('Privacy Policy', $footer, 'a chosen page that is not published is not linked');
        // Only existing pages can be chosen.
        foreach (['https://example.test/terms', 'no-such-page', '../etc'] as $bad) $this->actingAs($admin)->put(route('admin.settings.update'), ['terms_page' => $bad] + $base)->assertSessionHasErrors('terms_page');
        $legal->delete();
        $this->actingAs($admin)->put(route('admin.settings.update'), ['terms_page' => 'legal-notice'] + $base)->assertSessionHasErrors('terms_page');
        $this->assertStringNotContainsString('Terms of Service', $this->footer(), 'an archived chosen page is unlinked');
        // Back to automatic: the conventional address is used once that page is published.
        $this->actingAs($admin)->put(route('admin.settings.update'), ['terms_page' => ''] + $base)->assertSessionHasNoErrors();
        $this->page('terms-of-service');
        $this->assertStringContainsString('href="'.route('page.show', ['slug' => 'terms-of-service']).'">Terms of Service', $this->footer());
    }
    public function test_global_settings_explain_where_footer_details_appear(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        $this->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('Shown in the website footer under the phone number, with a location pin.')
            ->assertSee('Each profile appears as an icon in the website footer only once its web address is entered here.')
            ->assertSee('for="x_url"', false)->assertSee('for="contact_address"', false);
    }
}

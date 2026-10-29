<?php
namespace Tests\Feature;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BrandingDefaultsTest extends TestCase
{
    use RefreshDatabase;
    public function test_missing_branding_settings_fall_back_to_the_defaults(): void
    {
        $this->assertSame(Branding::DEFAULTS, Branding::apply(collect())->all());
    }
    public function test_empty_and_invalid_branding_settings_fall_back_to_the_defaults(): void
    {
        $settings = collect(['font_family' => '', 'button_style' => 'blob', 'primary_color' => 'red', 'terracotta_color' => '#12345', 'gold_color' => '#zzzzzz', 'earth_color' => null, 'water_color' => ['#1687c9'], 'site_name' => 'KELIKA']);
        $resolved = Branding::apply($settings);
        foreach (Branding::DEFAULTS as $key => $default) $this->assertSame($default, $resolved[$key], $key);
        $this->assertSame('KELIKA', $resolved['site_name']);
    }
    public function test_valid_configured_branding_is_kept(): void
    {
        $custom = ['font_family' => 'Georgia', 'button_style' => 'rounded', 'primary_color' => '#112233', 'space_color' => '#ABCDEF'];
        $resolved = Branding::apply(collect($custom));
        foreach ($custom as $key => $value) $this->assertSame($value, $resolved[$key]);
    }
    public function test_public_pages_render_with_missing_or_empty_branding_and_use_configured_values(): void
    {
        $this->withoutVite();
        $page = Page::create(['title' => 'Academy', 'slug' => 'academy', 'status' => 'published', 'published_at' => now()->subDay()]);
        PageSection::create(['page_id' => $page->id, 'type' => 'five_elements', 'data' => ['title' => 'Elements', 'items' => [['title' => 'Earth'], ['title' => 'Water']]]]);

        // No branding settings at all.
        $this->get('/academy')->assertOk()->assertSee("--kelika-font:'Inter'", false)->assertSee('--kelika-green:#064f43', false)->assertSee('--kelika-button-radius:3px', false)->assertSee('#176b4e', false);

        // Empty values, as a cleared settings field would store them; the five elements section must not get blank colours.
        foreach (['font_family', 'button_style', 'primary_color', 'earth_color', 'water_color'] as $key) Setting::write($key, '', 'branding');
        $this->get('/academy')->assertOk()->assertSee("--kelika-font:'Inter'", false)->assertSee('--element-earth:#176b4e', false)->assertSee('#1687c9', false);

        // Configured values win.
        Setting::write('font_family', 'Georgia', 'branding');
        Setting::write('button_style', 'soft', 'branding');
        Setting::write('primary_color', '#123456', 'branding');
        Setting::write('earth_color', '#654321', 'branding');
        $this->get('/academy')->assertOk()->assertSee("--kelika-font:'Georgia'", false)->assertSee('--kelika-button-radius:8px', false)->assertSee('--kelika-green:#123456', false)->assertSee('#654321', false)->assertDontSee('--kelika-green:#064f43', false);
    }
}

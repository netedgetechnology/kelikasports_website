<?php
namespace Tests\Feature;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
/** menu_items.url and pages.canonical_url are 255-character columns; longer values must be rejected, not crash. */
class UrlLengthValidationTest extends TestCase
{
    use RefreshDatabase;
    private Menu $menu;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        $this->menu = Menu::create(['name' => 'Main', 'location' => 'main', 'is_active' => true]);
    }
    /** A site path or URL of exactly $length characters. */
    private static function link(int $length, string $prefix = '/'): string
    {
        return $prefix.str_repeat('a', $length - mb_strlen($prefix));
    }
    private function addItem(string $url, string $label = 'Programs')
    {
        return $this->from(route('admin.menus.index'))->post(route('admin.menus.items.store', $this->menu), ['label' => $label, 'url' => $url, 'target' => '_blank', '_form' => 'menu-'.$this->menu->id.'-add']);
    }
    private function page(): Page
    {
        return Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'draft', 'seo_title' => 'Saved SEO title', 'canonical_url' => 'https://kelikasports.com/about']);
    }
    private function savePage(Page $page, array $fields)
    {
        return $this->from(route('admin.pages.edit', $page))->put(route('admin.pages.update', $page), $fields + ['title' => 'About', 'slug' => 'about', 'template' => 'standard', 'status' => 'draft', 'robots_directive' => 'index,follow', '_form' => 'page-settings']);
    }

    // Menu links
    public function test_a_menu_link_of_exactly_255_characters_is_saved(): void
    {
        $this->addItem($url = self::link(255))->assertSessionHasNoErrors();
        $this->assertSame($url, MenuItem::firstOrFail()->url);
        $this->addItem($multibyte = '/'.str_repeat('é', 254), 'Café')->assertSessionHasNoErrors(); // characters, not bytes
        $this->assertSame($multibyte, MenuItem::where('label', 'Café')->value('url'));
    }
    public function test_a_longer_menu_link_is_rejected_cleanly_and_the_form_keeps_what_was_typed(): void
    {
        $this->addItem(self::link(256), 'Junior programmes')->assertStatus(302)->assertRedirect(route('admin.menus.index'))
            ->assertSessionHasErrors(['url' => 'The link must be 255 characters or fewer.'])->assertSessionHasInput('label', 'Junior programmes');
        $this->assertSame(0, MenuItem::count());
        $this->get(route('admin.menus.index'))->assertOk()->assertSee('value="Junior programmes"', false)->assertSee('<option value="_blank" selected', false);
    }
    public function test_editing_a_menu_link_respects_the_same_limit_and_leaves_the_item_unchanged_on_failure(): void
    {
        $item = $this->menu->menuItems()->create(['label' => 'About', 'url' => '/about', 'target' => '_self', 'sort_order' => 1, 'is_visible' => true]);
        $other = $this->menu->menuItems()->create(['label' => 'Contact', 'url' => '/contact', 'target' => '_self', 'sort_order' => 2, 'is_visible' => true]);
        $update = fn (string $url) => $this->from(route('admin.menus.index'))->put(route('admin.menus.items.update', [$this->menu, $item]), ['label' => 'About us', 'url' => $url, 'target' => '_self', 'sort_order' => 1, 'is_visible' => 1, '_form' => 'menu-item-'.$item->id]);

        $update(self::link(256))->assertStatus(302)->assertSessionHasErrors(['url' => 'The link must be 255 characters or fewer.']);
        $this->assertSame(['About', '/about'], [$item->fresh()->label, $item->fresh()->url]);
        $html = $this->get(route('admin.menus.index'))->assertOk()->getContent();
        $this->assertStringContainsString('value="About us"', $html, 'the edited row shows what was typed');
        $this->assertStringContainsString('value="Contact"', $html, 'other rows show their saved values');

        $update($url = self::link(255))->assertSessionHasNoErrors();
        $this->assertSame([$url, '/contact'], [$item->fresh()->url, $other->fresh()->url]);
    }
    public static function invalidMenuLinks(): array
    {
        return ['script URL' => ['javascript:alert(1)'], 'protocol-relative' => ['//evil.example/x'], 'backslash' => ['/a\\b'], 'empty' => ['']];
    }
    #[DataProvider('invalidMenuLinks')]
    public function test_invalid_menu_link_formats_are_still_rejected(string $url): void
    {
        $this->addItem($url)->assertStatus(302)->assertSessionHasErrors('url');
        $this->assertSame(0, MenuItem::count());
    }

    // Canonical URLs
    public function test_a_canonical_url_of_exactly_255_characters_is_saved(): void
    {
        $page = $this->page();
        $this->savePage($page, ['canonical_url' => $url = self::link(255, 'https://kelikasports.com/')])->assertSessionHasNoErrors();
        $this->assertSame($url, $page->fresh()->canonical_url);
        $this->savePage($page, ['canonical_url' => ''])->assertSessionHasNoErrors();
        $this->assertNull($page->fresh()->canonical_url, 'clearing still works');
    }
    public function test_a_longer_canonical_url_is_rejected_cleanly_and_the_settings_form_keeps_what_was_typed(): void
    {
        $page = $this->page();
        $before = $page->fresh()->toArray();
        $this->savePage($page, ['canonical_url' => self::link(256, 'https://kelikasports.com/'), 'seo_title' => 'Unsaved new SEO title', 'show_in_menu' => '1'])
            ->assertStatus(302)->assertRedirect(route('admin.pages.edit', $page))
            ->assertSessionHasErrors(['canonical_url' => 'The canonical URL must be 255 characters or fewer.']);
        $this->assertSame($before, $page->fresh()->toArray(), 'nothing saved');
        $html = $this->get(route('admin.pages.edit', $page))->assertOk()->getContent();
        $this->assertStringContainsString('value="Unsaved new SEO title"', $html);
        $this->assertMatchesRegularExpression('/name="show_in_menu"[^>]*checked/', $html);
    }
    public static function invalidCanonicalUrls(): array
    {
        return ['relative path' => ['/about'], 'no scheme' => ['kelikasports.com/about'], 'plain text' => ['not a url'], 'script URL' => ['javascript:alert(1)']];
    }
    #[DataProvider('invalidCanonicalUrls')]
    public function test_invalid_canonical_url_formats_are_rejected_without_saving(string $url): void
    {
        $page = $this->page();
        $this->savePage($page, ['canonical_url' => $url])->assertStatus(302)
            ->assertSessionHasErrors(['canonical_url' => 'The canonical URL must be a complete web address, for example https://kelikasports.com/about.']);
        $this->assertSame('https://kelikasports.com/about', $page->fresh()->canonical_url);
    }
    public function test_new_pages_apply_the_same_canonical_limit(): void
    {
        $this->from(route('admin.pages.create'))->post(route('admin.pages.store'), ['title' => 'Camp', 'slug' => 'camp', 'template' => 'standard', 'status' => 'draft', 'robots_directive' => 'index,follow', 'canonical_url' => self::link(256, 'https://kelikasports.com/')])
            ->assertStatus(302)->assertSessionHasErrors('canonical_url');
        $this->assertSame(0, Page::count());
    }
}

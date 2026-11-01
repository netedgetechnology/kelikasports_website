<?php
namespace Tests\Feature;
use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use App\Support\PublicSiteData;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
/** Global settings validation and saving, and CMS user management. All values here live only in the test database. */
class SettingsAndUsersTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]);
        $this->actingAs($this->admin);
    }
    private function validSettings(array $overrides = []): array
    {
        $colors = array_fill_keys(['primary_color', 'terracotta_color', 'gold_color', 'earth_color', 'water_color', 'fire_color', 'air_color', 'space_color'], '#123abc');
        return $overrides + $colors + [
            'site_name' => 'Test Academy', 'site_tagline' => 'A tagline', 'booking_button_label' => 'Book now', 'font_family' => 'Georgia', 'button_style' => 'soft',
            'logo_url' => '/images/logo.png', 'footer_logo_url' => '', 'mobile_logo_url' => '', 'favicon_url' => 'https://cdn.example.test/favicon.png', 'social_image_url' => '',
            'contact_phone' => '+00 0000 0000', 'contact_email' => 'hello@example.test', 'contact_notification_email' => 'office@example.test', 'contact_address' => 'Test address',
            'contact_phone_hours' => 'Daily', 'contact_email_note' => 'Replies in a day', 'opening_hours' => 'Test hours', 'map_url' => 'https://maps.example.test/x', 'booking_url' => 'https://book.example.test', 'instagram_url' => '', 'facebook_url' => '', 'youtube_url' => '', 'linkedin_url' => '',
        ];
    }
    /** The named fields of the rendered settings form with their current values, as a browser would submit them. */
    private function formValues(): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$this->get(route('admin.settings.edit'))->assertOk()->getContent());
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);
        $form = $xpath->query('//form[@action="'.route('admin.settings.update').'"]')->item(0);
        $values = [];
        foreach ($xpath->query('.//input[@name]|.//textarea[@name]|.//select[@name]', $form) as $field) {
            $name = $field->getAttribute('name');
            $values[$name] = match ($field->nodeName) {
                'textarea' => $field->textContent,
                'select' => ($xpath->query('.//option[@selected]', $field)->item(0) ?? $xpath->query('.//option', $field)->item(0))?->getAttribute('value'),
                default => $field->getAttribute('value'),
            };
        }
        return $values;
    }

    // Settings

    public function test_valid_settings_are_saved_with_their_groups_and_clear_the_public_cache(): void
    {
        Cache::put(PublicSiteData::CACHE_KEY, ['stale' => true], 600);
        $this->from(route('admin.settings.edit'))->put(route('admin.settings.update'), $this->validSettings())->assertRedirect(route('admin.settings.edit'))->assertSessionHas('success', 'Global settings saved.');
        $this->assertSame(['Test Academy', 'Georgia', '#123abc', 'office@example.test', 'https://book.example.test'], array_map(fn ($k) => Setting::read($k), ['site_name', 'font_family', 'primary_color', 'contact_notification_email', 'booking_url']));
        $this->assertSame(['general', 'branding', 'contact', 'social'], array_map(fn ($k) => Setting::where('key', $k)->value('group'), ['site_name', 'primary_color', 'map_url', 'instagram_url']));
        $this->assertSame(['Daily', 'Replies in a day', 'contact', 'contact'], [Setting::read('contact_phone_hours'), Setting::read('contact_email_note'), Setting::where('key', 'contact_phone_hours')->value('group'), Setting::where('key', 'contact_email_note')->value('group')]);
        $this->assertNull(Setting::read('instagram_url'), 'an empty optional field is stored as empty');
        $this->assertFalse(Cache::has(PublicSiteData::CACHE_KEY));
    }
    public function test_the_settings_form_can_be_saved_unchanged(): void
    {
        $this->put(route('admin.settings.update'), $this->validSettings());
        $before = Setting::orderBy('key')->pluck('value', 'key')->all();
        $values = $this->formValues();
        $this->assertSame('', $values['footer_logo_url'], 'an empty logo setting is shown empty, as the site treats it');
        $this->assertArrayHasKey('primary_color', $values, 'colour fields are part of the form');
        $this->from(route('admin.settings.edit'))->put(route('admin.settings.update'), $values)->assertSessionHasNoErrors();
        $after = Setting::orderBy('key')->pluck('value', 'key')->all();
        // Empty logo fields used to be shown filled with the wordmark, so a save stored it and changed the footer.
        // Every existing value is unchanged; a field added since (such as the X profile) may now exist, but empty.
        $this->assertSame($before, array_intersect_key($after, $before));
        foreach (array_diff_key($after, $before) as $key => $value) $this->assertNull($value, "{$key} was created with a value");
    }
    public function test_invalid_settings_are_rejected_and_nothing_is_saved(): void
    {
        $this->put(route('admin.settings.update'), $this->validSettings());
        $before = Setting::orderBy('key')->pluck('value', 'key')->all();
        $invalid = ['site_name' => '', 'booking_button_label' => '', 'font_family' => 'Comic Sans', 'button_style' => 'pill', 'primary_color' => '#12345', 'gold_color' => 'red',
            'contact_email' => 'not-an-email', 'map_url' => 'not a url', 'booking_url' => 'javascript:alert(1)', 'instagram_url' => 'instagram', 'site_tagline' => str_repeat('t', 241)];
        $this->put(route('admin.settings.update'), $this->validSettings($invalid))->assertSessionHasErrors(array_keys($invalid));
        foreach (['javascript:alert(1)', '//evil.example/x.png', 'data:image/png;base64,AAAA'] as $unsafe) {
            $this->put(route('admin.settings.update'), $this->validSettings(['logo_url' => $unsafe]))->assertSessionHasErrors(['logo_url' => 'Use an uploaded media path or a valid http/https URL.']);
        }
        $this->assertSame($before, Setting::orderBy('key')->pluck('value', 'key')->all());
        $this->assertContains('Inter', Branding::FONTS);
    }
    public function test_the_notification_address_must_be_one_the_contact_form_can_send_to(): void
    {
        $this->put(route('admin.settings.update'), $this->validSettings(['contact_notification_email' => 'office@localhost']))->assertSessionHasErrors('contact_notification_email');
        $this->assertFalse(filter_var('office@localhost', FILTER_VALIDATE_EMAIL), 'the contact form would never send to it');
        $this->put(route('admin.settings.update'), $this->validSettings(['contact_notification_email' => '']))->assertSessionHasNoErrors();
        $this->assertNull(Setting::read('contact_notification_email'));
    }
    public function test_settings_need_a_signed_in_cms_user(): void
    {
        $this->app['auth']->forgetGuards();
        $this->get(route('admin.settings.edit'))->assertRedirect(route('login'));
        $this->put(route('admin.settings.update'), $this->validSettings())->assertRedirect(route('login'));
        $this->actingAs(User::create(['name' => 'Off', 'email' => 'off@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => false]));
        $this->put(route('admin.settings.update'), $this->validSettings())->assertForbidden();
        $this->assertSame(0, Setting::count());
    }

    // CMS users

    public function test_editors_and_visitors_cannot_manage_users(): void
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $new = ['name' => 'X', 'email' => 'x@example.test', 'role' => 'administrator', 'password' => 'a-long-enough-password', 'password_confirmation' => 'a-long-enough-password', 'is_active' => '1'];
        $this->actingAs($editor);
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.users.create'))->assertForbidden();
        $this->post(route('admin.users.store'), $new)->assertForbidden();
        $this->get(route('admin.users.edit', $editor))->assertForbidden();
        $this->put(route('admin.users.update', $editor), ['name' => 'Editor', 'email' => 'editor@example.test', 'role' => 'administrator', 'is_active' => '1'])->assertForbidden();
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.users.index'));
        $this->assertSame('editor', $editor->fresh()->role);
        $this->app['auth']->forgetGuards();
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->assertSame(2, User::count());
    }
    public function test_an_administrator_creates_a_user_with_a_hashed_password(): void
    {
        $this->get(route('admin.users.index'))->assertOk()->assertSee('admin@example.test');
        $this->post(route('admin.users.store'), ['name' => 'New Editor', 'email' => 'new@example.test', 'role' => 'editor', 'password' => 'a-long-enough-password', 'password_confirmation' => 'a-long-enough-password', 'is_active' => '1'])
            ->assertRedirect(route('admin.users.index'))->assertSessionHas('success', 'CMS user created.');
        $user = User::where('email', 'new@example.test')->sole();
        $this->assertSame(['New Editor', 'editor', true], [$user->name, $user->role, $user->is_active]);
        $this->assertNotSame('a-long-enough-password', $user->password);
        $this->assertTrue(Hash::check('a-long-enough-password', $user->password));
    }
    public function test_new_users_are_validated(): void
    {
        $base = ['name' => 'N', 'email' => 'n@example.test', 'role' => 'editor', 'password' => 'a-long-enough-password', 'password_confirmation' => 'a-long-enough-password'];
        $this->post(route('admin.users.store'), ['email' => 'admin@example.test'] + $base)->assertSessionHasErrors('email');
        $this->post(route('admin.users.store'), ['email' => 'not-an-email'] + $base)->assertSessionHasErrors('email');
        $this->post(route('admin.users.store'), ['password' => 'short-13-char', 'password_confirmation' => 'short-13-char'] + $base)->assertSessionHasErrors('password');
        $this->post(route('admin.users.store'), ['password_confirmation' => 'different-password!'] + $base)->assertSessionHasErrors('password');
        $this->post(route('admin.users.store'), ['role' => 'owner'] + $base)->assertSessionHasErrors('role');
        $this->post(route('admin.users.store'), ['name' => ''] + $base)->assertSessionHasErrors('name');
        $this->assertSame(1, User::count());
    }
    public function test_updating_a_user_keeps_the_password_unless_a_new_one_is_given(): void
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        $hash = $editor->password;
        $this->put(route('admin.users.update', $editor), ['name' => 'Renamed', 'email' => 'editor@example.test', 'role' => 'editor', 'is_active' => '1', 'password' => ''])->assertSessionHas('success', 'CMS user updated.');
        $this->assertSame(['Renamed', $hash], [$editor->fresh()->name, $editor->fresh()->password]);
        $this->put(route('admin.users.update', $editor), ['name' => 'Renamed', 'email' => 'editor@example.test', 'role' => 'editor', 'password' => 'another-long-password', 'password_confirmation' => 'another-long-password']);
        $this->assertTrue(Hash::check('another-long-password', $editor->fresh()->password));
        $this->assertFalse($editor->fresh()->is_active, 'an unticked "active" box deactivates the account');
        $this->put(route('admin.users.update', $editor), ['name' => 'Renamed', 'email' => 'admin@example.test', 'role' => 'editor', 'is_active' => '1'])->assertSessionHasErrors('email');
    }
    public function test_administrators_cannot_lock_themselves_or_everyone_out(): void
    {
        $self = ['name' => 'Admin', 'email' => 'admin@example.test', 'is_active' => '1', 'role' => 'administrator'];
        $this->put(route('admin.users.update', $this->admin), ['is_active' => ''] + $self)->assertSessionHasErrors(['role' => 'You cannot disable your own account or remove your own administrator role.']);
        $this->put(route('admin.users.update', $this->admin), ['role' => 'editor'] + $self)->assertSessionHasErrors('role');
        $other = User::create(['name' => 'Second', 'email' => 'second@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]);
        $this->put(route('admin.users.update', $other), ['name' => 'Second', 'email' => 'second@example.test', 'role' => 'editor', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('editor', $other->fresh()->role, 'another administrator can be changed while one remains');
        $this->assertTrue($this->admin->fresh()->isAdministrator(), 'the signed-in administrator is unchanged');
        // An inactive administrator account can be changed while an active one remains.
        $dormant = User::create(['name' => 'Dormant', 'email' => 'dormant@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => false]);
        $other->update(['role' => 'editor']);
        $this->put(route('admin.users.update', $dormant), ['name' => 'Dormant', 'email' => 'dormant@example.test', 'role' => 'editor'])->assertSessionHasNoErrors();
        $this->assertSame('editor', $dormant->fresh()->role);
    }
}

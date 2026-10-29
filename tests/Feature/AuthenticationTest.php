<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;
/** Signing in and out of the CMS, and resetting a forgotten password. */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;
    private const PASSWORD = 'correct-horse-battery-staple';
    private const SENT = 'If this email address belongs to a CMS account, a password reset link has been sent to it.';
    private User $editor;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => self::PASSWORD, 'role' => 'editor', 'is_active' => true]);
    }
    private function login(array $overrides = [], string $ip = '203.0.113.10')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->from(route('login'))->post(route('login.store'), $overrides + ['email' => 'editor@example.test', 'password' => self::PASSWORD]);
    }

    public function test_an_active_cms_user_signs_in_with_a_fresh_session_and_out_again(): void
    {
        $this->get(route('login'))->assertOk();
        $this->startSession();
        $before = session()->getId();
        $this->login()->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->editor);
        $this->assertNotSame($before, session()->getId(), 'the session id changes on sign-in');
        $this->get(route('login'))->assertRedirect(); // signed-in users are sent away from the login form
        $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionHas('status', 'You have been signed out.');
        $this->assertGuest();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }
    public function test_wrong_passwords_unknown_and_inactive_accounts_are_refused_alike(): void
    {
        $message = 'The supplied credentials are invalid or the account is inactive.';
        $this->login(['password' => 'wrong-password-123'])->assertSessionHasErrors(['email' => $message]);
        $this->login(['email' => 'nobody@example.test'])->assertSessionHasErrors(['email' => $message]);
        $this->editor->update(['is_active' => false]);
        $this->login()->assertSessionHasErrors(['email' => $message]);
        $this->assertGuest();
    }
    public function test_a_deactivated_user_loses_access_on_the_next_request(): void
    {
        $this->actingAs($this->editor)->get(route('admin.dashboard'))->assertOk();
        $this->editor->update(['is_active' => false]);
        $this->actingAs($this->editor->fresh())->get(route('admin.dashboard'))->assertForbidden();
    }
    public function test_repeated_failed_sign_ins_are_throttled(): void
    {
        foreach (range(1, 8) as $i) $this->login(['password' => 'wrong-password-123'])->assertSessionHasErrors('email');
        $this->login()->assertStatus(429);
        $this->assertGuest();
        $this->login([], '198.51.100.20')->assertRedirect(route('admin.dashboard'));
    }
    public function test_the_reset_form_gives_the_same_answer_for_any_address(): void
    {
        Notification::fake();
        $this->get(route('password.request'))->assertOk();
        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'editor@example.test'])->assertSessionHas('status', self::SENT)->assertSessionHasNoErrors();
        Notification::assertSentTo($this->editor, ResetPassword::class);
        // Unknown address, and a second request for a real one (throttled by the broker): indistinguishable.
        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'nobody@example.test'])->assertSessionHas('status', self::SENT)->assertSessionHasNoErrors();
        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'editor@example.test'])->assertSessionHas('status', self::SENT)->assertSessionHasNoErrors();
        Notification::assertSentToTimes($this->editor, ResetPassword::class, 1);
        $this->post(route('password.email'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    }
    public function test_a_valid_token_sets_a_new_password_of_at_least_14_characters(): void
    {
        $token = Password::createToken($this->editor);
        $reset = fn (array $data) => $this->from(route('password.reset', $token))->post(route('password.update'), $data + ['token' => $token, 'email' => 'editor@example.test']);
        $this->get(route('password.reset', $token))->assertOk();
        $reset(['password' => 'too-short-123', 'password_confirmation' => 'too-short-123'])->assertSessionHasErrors('password');
        $reset(['password' => 'a-brand-new-password', 'password_confirmation' => 'different-password'])->assertSessionHasErrors('password');
        $reset(['password' => 'a-brand-new-password', 'password_confirmation' => 'a-brand-new-password', 'token' => 'forged-token'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check(self::PASSWORD, $this->editor->fresh()->password));
        $reset(['password' => 'a-brand-new-password', 'password_confirmation' => 'a-brand-new-password'])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('a-brand-new-password', $this->editor->fresh()->password));
        $reset(['password' => 'yet-another-password', 'password_confirmation' => 'yet-another-password'])->assertSessionHasErrors('email', null, 'default');
        $this->assertTrue(Hash::check('a-brand-new-password', $this->editor->fresh()->password), 'a token works only once');
    }
}

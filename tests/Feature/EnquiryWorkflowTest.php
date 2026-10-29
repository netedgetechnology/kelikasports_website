<?php
namespace Tests\Feature;
use App\Models\Enquiry;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
/** The public contact form and the existing enquiry administration: submission, rate limits, notification, list, details, status, search and export. */
class EnquiryWorkflowTest extends TestCase
{
    use RefreshDatabase;
    private const IP = '203.0.113.5';
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
    private function valid(array $overrides = []): array
    {
        return $overrides + ['name' => 'Asha Rao', 'email' => 'asha@example.test', 'phone' => '+91 98765 43210', 'category' => 'Court Booking', 'message' => 'Is a court free on Saturday morning?', 'consent' => '1', 'source_page' => 'contact'];
    }
    private function submit(array $data, string $ip = self::IP)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->from('/contact')->post(route('contact.store'), $data);
    }
    private function user(string $role = 'administrator', bool $active = true): User
    {
        return User::create(['name' => ucfirst($role), 'email' => $role.($active ? '' : '-off').'@example.test', 'password' => 'correct-horse-battery-staple', 'role' => $role, 'is_active' => $active]);
    }
    private function enquiry(array $attributes = []): Enquiry
    {
        $e = Enquiry::create($attributes + ['name' => 'Ben', 'email' => 'ben@example.test', 'message' => 'Hello there', 'status' => 'new']);
        if (isset($attributes['created_at'])) $e->forceFill(['created_at' => $attributes['created_at']])->save();
        return $e;
    }
    /** Messages handed to the "array" mail transport the test configuration uses. */
    private function sentMail(): array
    {
        return iterator_to_array(Mail::mailer()->getSymfonyTransport()->messages());
    }

    // Public submission

    public function test_a_valid_submission_is_stored_as_a_new_enquiry(): void
    {
        $this->submit($this->valid())->assertRedirect('/contact')
            ->assertSessionHas('success', 'Thank you. Your message has been received. We will respond as soon as possible.');
        $e = Enquiry::sole();
        $this->assertSame(['Asha Rao', 'asha@example.test', '+91 98765 43210', 'Court Booking', 'Is a court free on Saturday morning?', 'new', 'contact'], [$e->name, $e->email, $e->phone, $e->category, $e->message, $e->status, $e->source_page]);
        $this->assertSame(hash_hmac('sha256', self::IP, (string) config('app.key')), $e->ip_hash, 'the address is kept only as a keyed hash');
        $this->assertNotNull($e->consented_at);
        $this->assertNull($e->internal_notes);
    }
    public function test_the_contact_form_carries_a_csrf_token_and_a_hidden_spam_trap(): void
    {
        $page = Page::create(['title' => 'Contact', 'slug' => 'contact', 'status' => 'published', 'published_at' => now()->subDay(), 'show_in_menu' => true]);
        PageSection::create(['page_id' => $page->id, 'type' => 'contact_form', 'sort_order' => 1, 'is_visible' => true, 'data' => []]);
        $this->get('/contact')->assertOk()->assertSee('action="'.route('contact.store').'"', false)
            ->assertSee('name="_token"', false)->assertSee('name="website" value="" tabindex="-1"', false)
            ->assertSee('name="consent" required', false);
    }
    public function test_required_fields_and_consent_are_enforced(): void
    {
        $this->submit([])->assertSessionHasErrors(['name', 'email', 'message', 'consent']);
        $this->submit($this->valid(['consent' => null]))->assertSessionHasErrors('consent');
        $this->submit($this->valid(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->assertSame(0, Enquiry::count());
    }
    public function test_field_lengths_are_limited(): void
    {
        $this->submit($this->valid(['name' => str_repeat('a', 121), 'message' => str_repeat('m', 5001), 'phone' => str_repeat('1', 41), 'category' => str_repeat('c', 101)]))
            ->assertSessionHasErrors(['name', 'message', 'phone', 'category']);
        $this->submit($this->valid(['name' => str_repeat('a', 120), 'message' => str_repeat('m', 5000)]))->assertSessionHasNoErrors();
        $this->submit($this->valid(['source_page' => str_repeat('p', 300)]), '198.51.100.1');
        $this->assertSame(str_repeat('p', 120).'...', Enquiry::latest('id')->first()->source_page);
    }
    public function test_spam_trap_submissions_look_successful_but_are_not_stored_or_sent(): void
    {
        Setting::write('contact_notification_email', 'office@example.test');
        $this->submit($this->valid(['website' => 'https://spam.example']))->assertSessionHas('success');
        $this->assertSame(0, Enquiry::count());
        $this->assertSame([], $this->sentMail());
    }
    public function test_five_submissions_per_visitor_are_accepted_every_ten_minutes(): void
    {
        foreach (range(1, 5) as $i) $this->submit($this->valid(['message' => "Message {$i}"]))->assertSessionHasNoErrors();
        $this->submit($this->valid())->assertSessionHasErrors(['form' => 'Too many messages were submitted. Please try again later.']);
        $this->assertSame(5, Enquiry::count());
        $this->submit($this->valid(), '198.51.100.9')->assertSessionHasNoErrors();
        $this->travel(11)->minutes();
        $this->submit($this->valid())->assertSessionHasNoErrors();
        $this->assertSame(7, Enquiry::count());
    }
    public function test_the_route_limit_stops_any_visitor_after_ten_requests_in_ten_minutes(): void
    {
        foreach (range(1, 10) as $i) $this->assertNotSame(429, $this->submit(['name' => 'x'])->status());
        $this->submit($this->valid())->assertStatus(429);
        $this->assertSame(0, Enquiry::count());
    }

    // Notification

    public function test_a_notification_is_sent_to_the_configured_address(): void
    {
        Setting::write('contact_notification_email', 'office@example.test');
        $this->submit($this->valid());
        $sent = $this->sentMail();
        $this->assertCount(1, $sent);
        $email = $sent[0]->getOriginalMessage();
        $this->assertSame('office@example.test', $email->getTo()[0]->getAddress());
        $this->assertSame('New KELIKA website enquiry', $email->getSubject());
        $this->assertStringContainsString('Is a court free on Saturday morning?', $email->getTextBody());
    }
    public function test_no_notification_is_sent_without_a_valid_address(): void
    {
        $this->submit($this->valid());
        Setting::write('contact_notification_email', 'not an address');
        $this->submit($this->valid(['message' => 'Second']));
        $this->assertSame([], $this->sentMail());
        $this->assertSame(2, Enquiry::count());
    }
    public function test_a_failing_notification_does_not_lose_the_enquiry(): void
    {
        Setting::write('contact_notification_email', 'office@example.test');
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->submit($this->valid())->assertSessionHas('success');
        $this->assertSame(1, Enquiry::count());
    }

    // Administration

    public function test_visitors_must_sign_in_and_users_without_cms_access_are_refused(): void
    {
        $e = $this->enquiry();
        foreach (['index' => [], 'show' => [$e], 'export' => []] as $route => $params) $this->get(route('admin.enquiries.'.$route, $params))->assertRedirect(route('login'));
        $this->put(route('admin.enquiries.update', $e), ['status' => 'resolved'])->assertRedirect(route('login'));
        $this->actingAs($this->user('editor', false));
        $this->get(route('admin.enquiries.index'))->assertForbidden();
        $this->put(route('admin.enquiries.update', $e), ['status' => 'resolved'])->assertForbidden();
        $this->assertSame('new', $e->fresh()->status);
    }
    public function test_the_list_shows_enquiries_newest_first_with_25_per_page(): void
    {
        foreach (range(1, 27) as $i) $this->enquiry(['email' => "person{$i}@example.test", 'created_at' => now()->subMinutes(100 - $i)]);
        $this->actingAs($this->user());
        $this->get(route('admin.enquiries.index'))->assertOk()->assertSeeInOrder(['person27@', 'person26@', 'person3@'])->assertDontSee('person2@')->assertSee('page=2', false);
        $this->get(route('admin.enquiries.index', ['page' => 2]))->assertSee('person2@')->assertSee('person1@');
    }
    public function test_details_show_every_submitted_field_without_changing_status(): void
    {
        $e = $this->enquiry(['phone' => '12345', 'category' => 'Membership', 'source_page' => 'about', 'message' => "Line one\nLine two"]);
        $this->actingAs($this->user('editor'));
        $this->get(route('admin.enquiries.show', $e))->assertOk()->assertSee(['Ben', 'mailto:ben@example.test', '12345', 'Membership', 'about', "Line one\nLine two"], false);
        $this->assertSame('new', $e->fresh()->status);
        $this->get(route('admin.enquiries.show', 999999))->assertNotFound();
    }
    public function test_status_and_internal_notes_can_be_updated_and_are_validated(): void
    {
        $e = $this->enquiry();
        $this->actingAs($this->user('editor'));
        $this->from(route('admin.enquiries.show', $e))->put(route('admin.enquiries.update', $e), ['status' => 'in_progress', 'internal_notes' => 'Called back, awaiting reply'])
            ->assertRedirect(route('admin.enquiries.show', $e))->assertSessionHas('success', 'Enquiry updated.');
        $this->assertSame(['in_progress', 'Called back, awaiting reply'], [$e->fresh()->status, $e->fresh()->internal_notes]);
        $this->put(route('admin.enquiries.update', $e), ['status' => 'archived'])->assertSessionHasErrors('status');
        $this->put(route('admin.enquiries.update', $e), ['status' => 'resolved', 'internal_notes' => str_repeat('n', 10001)])->assertSessionHasErrors('internal_notes');
        $this->assertSame('in_progress', $e->fresh()->status);
        $this->assertSame('Ben', $e->fresh()->name, 'submitted details cannot be edited');
    }
    public function test_search_covers_name_email_and_message_and_combines_with_the_status_filter(): void
    {
        $this->enquiry(['name' => 'Charu Mehta', 'email' => 'charu@example.test']);
        $this->enquiry(['name' => 'Dev', 'email' => 'dev@club.example', 'status' => 'resolved']);
        $this->enquiry(['name' => 'Esha', 'email' => 'esha@example.test', 'message' => 'Question about the summer camp']);
        $this->actingAs($this->user());
        $this->get(route('admin.enquiries.index', ['q' => 'mehta']))->assertSee('charu@')->assertDontSee('dev@club')->assertDontSee('esha@');
        $this->get(route('admin.enquiries.index', ['q' => 'club.example']))->assertSee('dev@club')->assertDontSee('charu@');
        $this->get(route('admin.enquiries.index', ['q' => 'summer camp']))->assertSee('esha@')->assertDontSee('charu@');
        $this->get(route('admin.enquiries.index', ['status' => 'resolved']))->assertSee('dev@club')->assertDontSee('charu@');
        $this->get(route('admin.enquiries.index', ['q' => 'example.test', 'status' => 'resolved']))->assertSee('No enquiries match these filters.');
    }
    public function test_the_csv_export_has_the_documented_columns_and_neutralises_formulas(): void
    {
        $this->enquiry(['name' => '=HYPERLINK("http://evil.example","x")', 'email' => 'f@example.test', 'phone' => '+1 555', 'category' => '@SUM(A1)', 'message' => "-2+3\nsecond line", 'internal_notes' => 'private note', 'ip_hash' => str_repeat('a', 64), 'source_page' => 'contact']);
        $this->actingAs($this->user('editor'));
        $response = $this->get(route('admin.enquiries.export'))->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression('/attachment; filename=kelika-enquiries-\d{8}-\d{6}\.csv/', $response->headers->get('Content-Disposition'));
        $rows = array_map('str_getcsv', explode("\n", trim(str_replace("\nsecond", ' second', $response->streamedContent()))));
        $this->assertSame(['ID', 'Name', 'Email', 'Phone', 'Category', 'Message', 'Status', 'Source Page', 'Created At'], $rows[0]);
        $this->assertSame(['\'=HYPERLINK("http://evil.example","x")', 'f@example.test', "'+1 555", "'@SUM(A1)", "'-2+3 second line", 'new', 'contact'], array_slice($rows[1], 1, 7));
        $this->assertStringNotContainsString('private note', $response->streamedContent());
        $this->assertStringNotContainsString(str_repeat('a', 64), $response->streamedContent());
    }
    public function test_the_dashboard_counts_new_enquiries(): void
    {
        $this->enquiry();
        $this->enquiry(['email' => 'x@example.test']);
        $this->enquiry(['status' => 'resolved', 'email' => 'y@example.test']);
        $this->actingAs($this->user());
        $this->assertSame(2, $this->get(route('admin.dashboard'))->assertOk()->viewData('enquiries'));
    }
}

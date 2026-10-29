<?php
namespace Tests\Feature;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\EnquiryDuplicates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
/** Rate-limit accounting, literal search, filtered export, date ranges, spam, possible duplicates, and soft deletion with restore. */
class EnquiryManagementTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private User $editor;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]);
        $this->editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
    }
    private function enquiry(array $attributes = []): Enquiry
    {
        $e = Enquiry::create($attributes + ['name' => 'Ben', 'email' => 'ben@example.test', 'message' => 'Hello there', 'status' => 'new']);
        if (isset($attributes['created_at'])) $e->forceFill(['created_at' => $attributes['created_at'], 'updated_at' => $attributes['created_at']])->save();
        return $e->fresh();
    }
    private function submit(array $overrides = [], string $ip = '203.0.113.5')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->from('/contact')->post(route('contact.store'), $overrides + ['name' => 'Asha', 'email' => 'asha@example.test', 'message' => 'Is a court free on Saturday?', 'consent' => '1']);
    }
    private function listed(array $query = []): array
    {
        preg_match_all('/<div class="small text-secondary">([^<]+@[^<]+)<\/div>/', $this->get(route('admin.enquiries.index', $query))->assertOk()->getContent(), $m);
        return $m[1];
    }
    private function exported(array $query = [], ?User $as = null): array
    {
        $this->actingAs($as ?? $this->admin);
        $rows = array_map('str_getcsv', explode("\n", trim($this->get(route('admin.enquiries.export', $query))->assertOk()->streamedContent())));
        return array_column(array_slice($rows, 1), 2);
    }
    /** Every column, so a test can show records were left exactly as they were. */
    private function rows(): array
    {
        return DB::table('enquiries')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    // Rate limiting

    public function test_invalid_submissions_do_not_use_up_the_five_enquiry_allowance(): void
    {
        foreach (range(1, 4) as $i) $this->submit(['email' => 'typo@'])->assertSessionHasErrors('email');
        foreach (range(1, 5) as $i) $this->submit(['message' => "Message {$i}"])->assertSessionHasNoErrors();
        $this->assertSame(5, Enquiry::count(), 'a visitor who corrected typos can still send five enquiries');
        $this->submit(['message' => 'Sixth'])->assertSessionHasErrors(['form' => 'Too many messages were submitted. Please try again later.']);
        $this->assertSame(5, Enquiry::count());
    }
    public function test_floods_of_invalid_submissions_are_still_stopped_by_the_route_limit(): void
    {
        foreach (range(1, 10) as $i) $this->submit(['email' => 'typo@'])->assertSessionHasErrors('email');
        $this->submit()->assertStatus(429);
        $this->assertSame(0, Enquiry::count());
    }

    // Search, filters and export

    public function test_percent_and_underscore_are_searched_literally(): void
    {
        $this->enquiry(['email' => 'percent@example.test', 'message' => 'We are 100% interested']);
        $this->enquiry(['email' => 'under@example.test', 'message' => 'Code: SUMMER_CAMP']);
        $this->enquiry(['email' => 'bang@example.test', 'message' => 'Wow! Great courts']);
        $this->enquiry(['email' => 'plain@example.test', 'message' => 'Nothing special 1000 times']);
        $this->actingAs($this->editor);
        $this->assertSame(['percent@example.test'], $this->listed(['q' => '%']));
        $this->assertSame(['percent@example.test'], $this->listed(['q' => '100%']));
        $this->assertSame(['under@example.test'], $this->listed(['q' => '_']));
        $this->assertSame(['under@example.test'], $this->listed(['q' => 'summer_camp']));
        $this->assertSame(['bang@example.test'], $this->listed(['q' => 'wow!']));
        $this->assertSame([], $this->listed(['q' => '1_0']));
    }
    public function test_the_export_uses_the_same_search_status_and_date_filters_as_the_list(): void
    {
        $this->enquiry(['email' => 'a@example.test', 'message' => 'Court booking', 'created_at' => '2026-10-01 10:00:00']);
        $this->enquiry(['email' => 'b@example.test', 'message' => 'Court booking', 'status' => 'resolved', 'created_at' => '2026-10-05 10:00:00']);
        $this->enquiry(['email' => 'c@example.test', 'message' => 'Membership', 'created_at' => '2026-10-09 10:00:00']);
        $this->enquiry(['email' => 'd@example.test', 'message' => 'Court spam', 'status' => 'spam', 'created_at' => '2026-10-09 11:00:00']);
        $this->enquiry(['email' => 'e@example.test', 'message' => 'Court booking', 'created_at' => '2026-10-08 10:00:00'])->delete();
        foreach ([[], ['q' => 'court'], ['status' => 'resolved'], ['status' => 'spam'], ['from' => '2026-10-05'], ['to' => '2026-10-05'], ['q' => 'court', 'from' => '2026-10-02', 'to' => '2026-10-09']] as $filters) {
            $this->actingAs($this->editor);
            $listed = $this->listed($filters);
            $this->assertSame($listed, $this->exported($filters, $this->editor), 'filters '.json_encode($filters));
        }
        $this->assertSame(['c@example.test', 'b@example.test', 'a@example.test'], $this->exported(), 'the default export leaves out spam and deleted enquiries, newest first');
        $this->assertSame(['b@example.test'], $this->exported(['q' => 'court', 'from' => '2026-10-02', 'to' => '2026-10-09']));
    }
    public function test_the_index_export_link_carries_the_current_filters(): void
    {
        $this->actingAs($this->editor);
        $this->get(route('admin.enquiries.index', ['q' => 'court', 'status' => 'spam', 'from' => '2026-10-01']))
            ->assertSee('href="'.e(route('admin.enquiries.export', ['q' => 'court', 'status' => 'spam', 'from' => '2026-10-01'])).'">Export filtered CSV', false);
        $this->get(route('admin.enquiries.index'))->assertSee('href="'.route('admin.enquiries.export').'">Export CSV', false);
    }
    public function test_a_large_export_is_read_in_batches_and_keeps_every_row_in_order(): void
    {
        $now = now();
        foreach (array_chunk(array_map(fn ($i) => ['name' => "N{$i}", 'email' => "p{$i}@example.test", 'message' => 'Hi', 'status' => 'new', 'created_at' => $now->copy()->subMinutes($i), 'updated_at' => $now], range(1, 1203)), 100) as $chunk) DB::table('enquiries')->insert($chunk);
        $queries = 0;
        DB::listen(function ($query) use (&$queries) { if (str_contains($query->sql, 'from "enquiries"')) $queries++; });
        $emails = $this->exported();
        $this->assertCount(1203, $emails);
        $this->assertSame(['p1@example.test', 'p2@example.test'], array_slice($emails, 0, 2));
        $this->assertSame('p1203@example.test', end($emails));
        $this->assertGreaterThanOrEqual(3, $queries, 'rows are fetched 500 at a time');
    }
    public function test_dates_cover_whole_local_days_and_invalid_filters_are_rejected(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'));
        $this->enquiry(['email' => 'late@example.test', 'created_at' => '2026-10-10 23:59:59']);
        $this->enquiry(['email' => 'early@example.test', 'created_at' => '2026-10-11 00:00:00']);
        $this->enquiry(['email' => 'next@example.test', 'created_at' => '2026-10-12 00:00:00']);
        $this->actingAs($this->editor);
        $this->assertSame(['early@example.test'], $this->listed(['from' => '2026-10-11', 'to' => '2026-10-11']));
        $this->assertSame(['early@example.test', 'late@example.test'], $this->listed(['to' => '2026-10-11']));
        $this->assertSame(['next@example.test', 'early@example.test'], $this->listed(['from' => '2026-10-11']));
        $this->get(route('admin.enquiries.index', ['from' => '2026-10-12', 'to' => '2026-10-11']))->assertSessionHasErrors(['to' => 'The end date must be on or after the start date.']);
        $this->get(route('admin.enquiries.index', ['from' => '11/10/2026']))->assertSessionHasErrors('from');
        $this->get(route('admin.enquiries.index', ['status' => 'deleted']))->assertSessionHasErrors('status');
        $this->get(route('admin.enquiries.export', ['status' => 'bogus']))->assertSessionHasErrors('status');
    }

    // Spam

    public function test_spam_is_hidden_from_the_list_and_dashboard_but_can_be_filtered_and_reversed(): void
    {
        $spam = $this->enquiry(['email' => 'spammer@example.test']);
        $this->enquiry(['email' => 'real@example.test']);
        $this->actingAs($this->editor);
        $this->put(route('admin.enquiries.update', $spam), ['status' => 'spam'])->assertSessionHasNoErrors();
        $this->assertSame(['real@example.test'], $this->listed());
        $this->assertSame(['spammer@example.test'], $this->listed(['status' => 'spam']));
        $this->get(route('admin.enquiries.index'))->assertSee('<option value="spam" >Spam</option>', false)->assertSee('All except spam');
        $dashboard = $this->get(route('admin.dashboard'));
        $this->assertSame(1, $dashboard->viewData('enquiries'));
        $this->assertSame(['real@example.test'], $dashboard->viewData('recentEnquiries')->pluck('email')->all());
        foreach (['in_progress', 'resolved', 'new'] as $status) {
            $this->put(route('admin.enquiries.update', $spam), ['status' => $status])->assertSessionHasNoErrors();
            $this->assertSame($status, $spam->fresh()->status);
        }
        $this->assertSame(2, $this->get(route('admin.dashboard'))->viewData('enquiries'));
        $this->put(route('admin.enquiries.update', $spam), ['status' => 'junk'])->assertSessionHasErrors('status');
        $this->get(route('admin.enquiries.show', $spam))->assertSee('<option value="spam" >Spam</option>', false);
    }

    // Possible duplicates

    public function test_the_same_email_and_message_within_seven_days_is_flagged_but_stored(): void
    {
        $this->submit(['email' => 'Asha@Example.test', 'message' => "Is a court free\non Saturday?"])->assertSessionHas('success', 'Thank you. Your message has been received. We will respond as soon as possible.');
        $this->submit(['email' => ' asha@example.test ', 'message' => 'is a court   free on saturday? '])->assertSessionHas('success', 'Thank you. Your message has been received. We will respond as soon as possible.');
        $this->assertSame(2, Enquiry::count(), 'both submissions are kept');
        [$first, $second] = Enquiry::orderBy('id')->get()->all();
        $this->actingAs($this->editor);
        $this->get(route('admin.enquiries.index'))->assertSee('data-possible-duplicate="'.$first->id.'"', false)->assertSee('data-possible-duplicate="'.$second->id.'"', false)->assertSee('Possible duplicate (1)');
        $this->get(route('admin.enquiries.show', $second))->assertSee('data-possible-duplicates', false)->assertSee(route('admin.enquiries.show', $first));
    }
    public function test_different_messages_other_senders_and_older_matches_are_not_flagged(): void
    {
        $base = Carbon::parse('2026-10-10 12:00:00');
        $a = $this->enquiry(['created_at' => $base]);
        $within = $this->enquiry(['created_at' => $base->copy()->addDays(7)]);
        $outside = $this->enquiry(['created_at' => $base->copy()->addDays(14)]);
        $pairA = $this->enquiry(['email' => 'pair@example.test', 'created_at' => $base]);
        $pairB = $this->enquiry(['email' => 'pair@example.test', 'created_at' => $base->copy()->addDays(7)->addSecond()]);
        $otherMessage = $this->enquiry(['message' => 'Hello there, again', 'created_at' => $base]);
        $otherSender = $this->enquiry(['email' => 'someone@example.test', 'created_at' => $base]);
        $spamCopy = $this->enquiry(['status' => 'spam', 'created_at' => $base->copy()->subDay()]);
        $deletedCopy = $this->enquiry(['created_at' => $base->copy()->addDay()]);
        $deletedCopy->delete();
        $found = EnquiryDuplicates::for(Enquiry::all());
        $this->assertSame([$spamCopy->id, $within->id], $found[$a->id]->pluck('id')->all(), 'spam counts as a match; deleted and 14-day-old copies do not');
        $this->assertSame([$a->id, $outside->id], $found[$within->id]->pluck('id')->all(), 'exactly seven days apart still matches');
        $this->assertSame([$within->id], $found[$outside->id]->pluck('id')->all());
        $this->assertArrayNotHasKey($pairA->id, $found, 'seven days and one second apart does not match');
        $this->assertArrayNotHasKey($pairB->id, $found);
        $this->assertArrayNotHasKey($otherMessage->id, $found);
        $this->assertArrayNotHasKey($otherSender->id, $found);
        $this->assertSame(EnquiryDuplicates::WINDOW_DAYS, 7);
    }
    public function test_duplicate_flags_on_the_list_take_a_fixed_number_of_queries(): void
    {
        $this->actingAs($this->editor);
        $count = function () {
            DB::flushQueryLog(); DB::enableQueryLog();
            $this->get(route('admin.enquiries.index'))->assertOk();
            return collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"enquiries"'))->count();
        };
        foreach (range(1, 3) as $i) $this->enquiry(['email' => "p{$i}@example.test"]);
        $few = $count();
        foreach (range(4, 25) as $i) $this->enquiry(['email' => "p{$i}@example.test", 'message' => $i % 2 ? 'Same' : "Text {$i}"]);
        $this->assertSame($few, $count());
    }

    // Soft deletion and restore

    public function test_an_administrator_deletes_an_enquiry_without_losing_its_data(): void
    {
        $e = $this->enquiry(['email' => 'gone@example.test', 'message' => 'Court booking', 'status' => 'in_progress', 'internal_notes' => 'Called', 'created_at' => '2026-10-01 09:00:00']);
        $this->enquiry(['email' => 'kept@example.test', 'message' => 'Court booking']);
        $before = DB::table('enquiries')->where('id', $e->id)->first();
        $this->actingAs($this->admin);
        $this->get(route('admin.enquiries.show', $e))->assertSee('data-confirm="Delete the enquiry from Ben? You can restore it later from Deleted enquiries."', false);
        $this->delete(route('admin.enquiries.destroy', $e))->assertRedirect(route('admin.enquiries.index'))->assertSessionHas('success');

        $after = DB::table('enquiries')->where('id', $e->id)->first();
        $this->assertNotNull($after->deleted_at, 'the row is still in the database');
        $this->assertSame(array_diff_key((array) $before, ['deleted_at' => 1]), array_diff_key((array) $after, ['deleted_at' => 1]));
        $this->assertSame(['kept@example.test'], $this->listed());
        $this->assertSame([], $this->listed(['q' => 'gone']));
        $this->assertSame([], $this->listed(['status' => 'in_progress']));
        $this->assertSame(['kept@example.test'], $this->exported());
        $this->assertSame(1, $this->get(route('admin.dashboard'))->viewData('enquiries'));
        $this->get(route('admin.enquiries.show', $e))->assertNotFound();
        $this->get(route('admin.enquiries.index'))->assertSee('Deleted enquiries (1)');
    }
    public function test_an_administrator_restores_an_enquiry_exactly_as_it_was(): void
    {
        $e = $this->enquiry(['status' => 'resolved', 'internal_notes' => 'Booked court 3', 'created_at' => '2026-10-01 09:00:00']);
        $before = DB::table('enquiries')->where('id', $e->id)->first();
        $this->actingAs($this->admin);
        $this->delete(route('admin.enquiries.destroy', $e));
        $this->get(route('admin.enquiries.deleted'))->assertOk()->assertSee('ben@example.test')->assertSee('action="'.route('admin.enquiries.restore', $e).'"', false);
        $this->post(route('admin.enquiries.restore', $e))->assertRedirect(route('admin.enquiries.show', $e))->assertSessionHas('success', 'Enquiry restored.');
        $this->assertEquals($before, DB::table('enquiries')->where('id', $e->id)->first(), 'status, notes and both timestamps are unchanged');
        $this->assertSame(['ben@example.test'], $this->listed(['status' => 'resolved']));
        $this->get(route('admin.enquiries.deleted'))->assertSee('No deleted enquiries.');
        $this->post(route('admin.enquiries.restore', $e))->assertNotFound();
    }
    public function test_editors_can_view_and_export_but_cannot_delete_restore_or_see_deleted_enquiries(): void
    {
        $e = $this->enquiry();
        $gone = $this->enquiry(['email' => 'gone@example.test']);
        $gone->delete();
        $before = $this->rows();
        $this->actingAs($this->editor);
        $this->get(route('admin.enquiries.show', $e))->assertOk()->assertDontSee('Delete enquiry');
        $this->get(route('admin.enquiries.index'))->assertDontSee('Deleted enquiries');
        $this->assertSame(['ben@example.test'], $this->exported([], $this->editor));
        $this->delete(route('admin.enquiries.destroy', $e))->assertForbidden();
        $this->post(route('admin.enquiries.restore', $gone))->assertForbidden();
        $this->get(route('admin.enquiries.deleted'))->assertForbidden();
        $this->assertSame($before, $this->rows());
    }
    public function test_unknown_ids_and_signed_out_requests_change_nothing(): void
    {
        $e = $this->enquiry();
        $before = $this->rows();
        $this->delete(route('admin.enquiries.destroy', $e))->assertRedirect(route('login'));
        $this->post(route('admin.enquiries.restore', $e))->assertRedirect(route('login'));
        $this->get(route('admin.enquiries.deleted'))->assertRedirect(route('login'));
        $this->actingAs($this->admin);
        $this->delete(route('admin.enquiries.destroy', 999999))->assertNotFound();
        $this->post(route('admin.enquiries.restore', 999999))->assertNotFound();
        $this->delete('/admin/enquiries/abc')->assertNotFound();
        $this->assertSame($before, $this->rows());
    }
    public function test_existing_records_are_untouched_by_listing_filtering_exporting_and_status_checks(): void
    {
        foreach (range(1, 6) as $i) $this->enquiry(['email' => "p{$i}@example.test", 'status' => array_keys(Enquiry::STATUSES)[$i % 4], 'internal_notes' => "Note {$i}", 'created_at' => "2026-10-0{$i} 10:00:00"]);
        $before = $this->rows();
        $this->actingAs($this->editor);
        foreach ([[], ['status' => 'spam'], ['q' => 'p1'], ['from' => '2026-10-02']] as $filters) { $this->listed($filters); $this->exported($filters, $this->editor); }
        foreach (Enquiry::all() as $e) $this->get(route('admin.enquiries.show', $e))->assertOk();
        $this->get(route('admin.dashboard'))->assertOk();
        $this->assertSame($before, $this->rows());
    }
}

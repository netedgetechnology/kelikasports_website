<?php
namespace Tests\Feature;
use App\Models\Enquiry;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/**
 * The Contact page design (design-reference/Kelika Sports - Contact Page.png): the contact form "split" layout with the
 * Location section beside it, quick enquiry cards with the FAQ section beside them, and hero contact methods. Contact
 * details come only from Site Settings; the form keeps its fields, validation and enquiry storage.
 */
class ContactLayoutsTest extends TestCase
{
    use RefreshDatabase;
    private Page $page;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->page = Page::create(['title' => 'Contact', 'slug' => 'contact', 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard']);
    }
    private function section(string $type, array $data, bool $visible = true): PageSection
    {
        return PageSection::create(['page_id' => $this->page->id, 'type' => $type, 'sort_order' => PageSection::count() + 1, 'is_visible' => $visible, 'data' => $data]);
    }
    private function settings(array $values): void
    {
        foreach ($values as $key => $value) Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        cache()->flush();
    }
    private function html(string $url = '/contact'): string
    {
        return $this->get($url)->assertOk()->getContent();
    }
    private function formAndLocation(): void
    {
        $this->section('contact_form', ['layout' => 'split', 'eyebrow' => 'SEND US A MESSAGE', 'title' => 'Get in *Touch*', 'subtitle' => 'Fill in the form below.', 'button_label' => 'Send Message']);
        $this->section('map_embed', ['layout' => 'beside', 'eyebrow' => 'OUR LOCATION', 'title' => 'Visit KELIKA', 'body' => 'Contact our team to arrange a visit.']);
    }
    private function enquiriesAndFaq(): void
    {
        $this->section('feature_list', ['layout' => 'enquiries', 'eyebrow' => 'QUICK ENQUIRIES', 'title' => 'How Can We *Help You?*', 'body' => 'Choose a topic.', 'items' => [
            ['title' => 'Court|Booking', 'icon' => 'calendar', 'url' => '/contact?topic=Court%20Booking#contact-form'],
            ['title' => 'Partnerships &|Collaborations', 'icon' => 'handshake', 'url' => '/contact?topic=Partnerships#contact-form'],
            ['title' => 'No Link', 'icon' => 'chat'],
        ]]);
        $this->section('faq', ['layout' => 'beside', 'eyebrow' => 'FREQUENTLY ASKED QUESTIONS', 'title' => 'Quick *Answers*', 'items' => [
            ['question' => 'How can I book a badminton court?', 'answer' => 'Send us a message with “Court Booking” as the topic.'],
            ['question' => 'Do you offer trial sessions?', 'answer' => 'Please contact the team.'],
            ['question' => '', 'answer' => 'Ignored'],
        ]]);
    }
    public function test_the_split_form_keeps_every_field_and_protection_and_shows_only_configured_details(): void
    {
        $this->settings(['contact_phone' => '7874028876']);
        $this->formAndLocation();
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-contact" id="contact-form">', $html);
        $this->assertStringContainsString('action="'.route('contact.store').'"', $html);
        $this->assertMatchesRegularExpression('#<input type="hidden" name="_token" value="[^"]+"#', $html);
        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringContainsString('<input type="hidden" name="source_page" value="contact">', $html);
        foreach (['name="name"', 'name="email"', 'name="phone"', 'name="category"', 'name="message"', 'name="consent"'] as $field) $this->assertStringContainsString($field, $html);
        $this->assertStringContainsString('data-char-count="contact-message-count"', $html);
        $this->assertStringContainsString('0 / 5000', $html);
        $this->assertStringContainsString('<span class="kl-heading__highlight">Touch</span>', $html);
        // Location column: copy from the Location section; only the configured phone; no map, address, email or hours.
        $this->assertStringContainsString('<p class="kl-contact__intro">Contact our team to arrange a visit.</p>', $html);
        // A bare 10-digit Indian number is shown grouped with the country code, as in the reference.
        $this->assertStringContainsString('<a href="tel:+917874028876">+91 78740 28876</a>', $html);
        $this->assertStringContainsString('Location details will be shared here soon.', $html);
        foreach (['<iframe', 'Get Directions', 'Email Us', 'Visit Hours', 'mailto:', 'kl-contact__address'] as $absent) $this->assertStringNotContainsString($absent, $html);
        // The Location section is not shown a second time on its own.
        $this->assertSame(1, substr_count($html, 'Visit KELIKA'));
        $this->assertStringNotContainsString('Map location to be confirmed', $html);
    }
    public function test_configured_address_map_email_and_hours_appear_in_the_location_column(): void
    {
        $map = 'https://www.google.com/maps/embed?pb=example';
        $this->settings(['contact_phone' => '+91 78740 28876', 'contact_email' => 'hello@example.test', 'contact_address' => "Sports Complex\nCity", 'opening_hours' => 'Mon – Sun', 'map_url' => $map]);
        $this->formAndLocation();
        $html = $this->html();
        $this->assertStringContainsString('<iframe title="KELIKA location map" src="'.e($map).'"', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(e($map), '#').'" target="_blank" rel="noopener noreferrer">Get Directions#', $html);
        $this->assertStringContainsString('<strong class="kl-contact__address-name">Sports Complex</strong><br>City', $html);
        $this->assertStringContainsString('<a href="tel:+917874028876">+91 78740 28876</a>', $html);
        $this->assertStringContainsString('<a href="mailto:hello@example.test">hello@example.test</a>', $html);
        $this->assertStringContainsString('<h3 class="kl-contact__detail-title">Visit Hours</h3><p>Mon – Sun</p>', $html);
        $this->assertStringNotContainsString('Location details will be shared here soon.', $html);
    }
    public function test_without_a_map_url_the_address_is_shown_but_no_directions_link_is_invented(): void
    {
        $this->settings(['contact_address' => "KELIKA Sports Complex\nNear Science City, Sola\nAhmedabad, Gujarat, India"]);
        $this->formAndLocation();
        $html = $this->html();
        $this->assertStringContainsString('<p class="kl-contact__address-text"><strong class="kl-contact__address-name">KELIKA Sports Complex</strong><br>Near Science City, Sola<br>Ahmedabad, Gujarat, India</p>', $html);
        foreach (['Get Directions', 'google.com/maps', '<iframe', 'kl-contact__map-text'] as $absent) $this->assertStringNotContainsString($absent, $html);
        $this->assertStringContainsString('kl-contact__map--empty', $html);
    }
    public function test_address_and_map_url_saved_in_cms_settings_appear_on_the_contact_page(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('id="contact_address" name="contact_address"', false)->assertSee('id="map_url" name="map_url"', false);
        $colors = array_fill_keys(['primary_color', 'terracotta_color', 'gold_color', 'earth_color', 'water_color', 'fire_color', 'air_color', 'space_color'], '#123abc');
        $map = 'https://maps.example.test/place/kelika';
        $this->put(route('admin.settings.update'), $colors + ['site_name' => 'KELIKA', 'booking_button_label' => 'Book a Court', 'font_family' => 'Inter', 'button_style' => 'square',
            'contact_address' => "Venue Name\nArea\nCity", 'map_url' => $map])->assertSessionHasNoErrors();
        auth()->logout();
        $this->formAndLocation();
        $html = $this->html();
        $this->assertStringContainsString('<strong class="kl-contact__address-name">Venue Name</strong><br>Area<br>City', $html);
        $this->assertStringContainsString('<a class="btn btn-kelika-outline kl-contact__directions" href="'.$map.'" target="_blank" rel="noopener noreferrer">Get Directions', $html);
        // A link that is not a Google Maps embed opens directions but does not replace the placeholder with a map.
        $this->assertStringNotContainsString('<iframe', $html);
    }
    public function test_a_single_line_address_is_shown_without_a_bold_name(): void
    {
        $this->settings(['contact_address' => 'Sola, Ahmedabad']);
        $this->formAndLocation();
        $html = $this->html();
        $this->assertStringContainsString('<p class="kl-contact__address-text">Sola, Ahmedabad</p>', $html);
        $this->assertStringNotContainsString('kl-contact__address-name', $html);
    }
    public function test_phone_hours_email_note_and_bracketed_hours_appear_as_notes_under_their_details(): void
    {
        $this->settings(['contact_phone' => '7874028876', 'contact_phone_hours' => "Mon – Sun\n8:00 AM – 9:00 PM", 'contact_email' => 'info@example.test', 'contact_email_note' => 'We usually respond within 24 hours', 'opening_hours' => "Mon – Sun\n6:00 AM – 10:00 PM\n(Subject to bookings)"]);
        $this->formAndLocation();
        $html = $this->html();
        $this->assertStringContainsString('<a href="tel:+917874028876">+91 78740 28876</a><p class="kl-contact__detail-note">Mon – Sun<br />'."\n".'8:00 AM – 9:00 PM</p>', $html);
        $this->assertStringContainsString('<a href="mailto:info@example.test">info@example.test</a><p class="kl-contact__detail-note">We usually respond within 24 hours</p>', $html);
        $this->assertStringContainsString('<h3 class="kl-contact__detail-title">Visit Hours</h3><p>Mon – Sun<br>6:00 AM – 10:00 PM</p><p class="kl-contact__detail-note">(Subject to bookings)</p>', $html);
    }
    public function test_notes_are_left_out_when_not_set_and_other_phone_formats_are_kept(): void
    {
        $this->settings(['contact_phone' => '+44 20 7946 0000', 'contact_email' => 'info@example.test', 'opening_hours' => "(By appointment)\nWeekdays"]);
        $this->formAndLocation();
        $html = $this->html();
        $this->assertStringContainsString('<a href="tel:+442079460000">+44 20 7946 0000</a></div>', $html);
        $this->assertStringContainsString('<a href="mailto:info@example.test">info@example.test</a></div>', $html);
        // Line order is kept when a bracketed line comes first.
        $this->assertStringContainsString('<p class="kl-contact__detail-note">(By appointment)</p><p>Weekdays</p>', $html);
    }
    public function test_a_topic_in_the_url_preselects_the_subject_and_unknown_topics_are_ignored(): void
    {
        $this->formAndLocation();
        $this->assertStringContainsString('<option value="Court Booking" selected>', $this->html('/contact?topic=Court%20Booking'));
        $this->assertStringContainsString('<option value="Partnerships" selected>', $this->html('/contact?topic=Partnerships'));
        $other = $this->html('/contact?topic=Free%20Courts');
        $this->assertStringNotContainsString(' selected>', $other);
        $this->assertStringNotContainsString('Free Courts', $other);
    }
    public function test_a_valid_message_is_stored_with_its_subject_and_the_success_message_shows_in_the_split_form(): void
    {
        $this->formAndLocation();
        $this->from('/contact?topic=Court%20Booking')->post(route('contact.store'), [
            'name' => 'Asha', 'email' => 'asha@example.test', 'phone' => '12345', 'category' => 'Court Booking', 'message' => 'Is a court free on Sunday?', 'consent' => '1', 'source_page' => 'contact',
        ])->assertRedirect('/contact?topic=Court%20Booking')->assertSessionHasNoErrors();
        $enquiry = Enquiry::sole();
        $this->assertSame(['Asha', 'Court Booking', 'contact', 'new'], [$enquiry->name, $enquiry->category, $enquiry->source_page, $enquiry->status]);
        $this->assertNotNull($enquiry->consented_at);
        $this->assertStringContainsString('Thank you. Your message has been received.', $this->html('/contact?topic=Court%20Booking'));
    }
    public function test_invalid_messages_and_missing_consent_are_rejected_with_errors_shown_beside_the_fields(): void
    {
        $this->formAndLocation();
        $this->from('/contact')->post(route('contact.store'), ['name' => '', 'email' => 'not-an-email', 'message' => '', 'category' => 'Membership'])
            ->assertRedirect('/contact')->assertSessionHasErrors(['name', 'email', 'message', 'consent']);
        $this->assertSame(0, Enquiry::count());
        $html = $this->html();
        $this->assertStringContainsString('id="contact-name-error"', $html);
        $this->assertStringContainsString('id="contact-email-error"', $html);
        $this->assertMatchesRegularExpression('#class="form-check-input\s+is-invalid\s*" type="checkbox"#', $html);
        $this->assertStringContainsString('<option value="Membership" selected>', $html, 'the chosen subject is kept');
        // Honeypot submissions are accepted quietly and never stored.
        $this->from('/contact')->post(route('contact.store'), ['website' => 'bot', 'name' => 'Bot', 'email' => 'bot@example.test', 'message' => 'Spam', 'consent' => '1']);
        $this->assertSame(0, Enquiry::count());
    }
    public function test_quick_enquiry_cards_link_to_preselected_subjects_beside_the_faq_accordion(): void
    {
        $this->enquiriesAndFaq();
        $html = $this->html();
        $this->assertStringContainsString('<section class="kl-section kl-help" id="quick-enquiries">', $html);
        $this->assertMatchesRegularExpression('#href="/contact\?topic=Court%20Booking\#contact-form">\s*Court<br>\s*Booking#', $html);
        $this->assertMatchesRegularExpression('#href="/contact\?topic=Partnerships\#contact-form">\s*Partnerships &amp;<br>\s*Collaborations#', $html);
        $this->assertSame(2, substr_count($html, 'class="kl-help-card__arrow"'), 'only cards with a link get an arrow');
        $this->assertStringContainsString(\App\Support\Icon::svg('handshake', 'kl-icon kl-icon--xl'), $html);
        $this->assertStringContainsString('<div class="kl-help__faq" id="frequently-asked-questions">', $html);
        $this->assertSame(2, substr_count($html, '<details class="kl-faq__item">'));
        $this->assertStringContainsString('<span>Do you offer trial sessions?</span>', $html);
        $this->assertStringNotContainsString('Ignored', $html);
        // The FAQ section is not repeated on its own.
        $this->assertSame(1, substr_count($html, 'How can I book a badminton court?'));
    }
    public function test_companion_sections_render_on_their_own_when_their_host_is_missing_or_hidden(): void
    {
        $this->section('feature_list', ['layout' => 'enquiries', 'title' => 'Help'], false);
        $this->section('faq', ['layout' => 'beside', 'title' => 'Quick Answers', 'items' => [['question' => 'Standalone question?', 'answer' => 'Yes.']]]);
        $html = $this->html();
        $this->assertStringContainsString('Standalone question?', $html);
        $this->assertStringContainsString('<div class="faq-row">', $html);
        $this->assertStringNotContainsString('kl-help', $html);
    }
    public function test_default_layouts_are_unchanged(): void
    {
        $this->section('contact_form', ['title' => 'Get in Touch']);
        $this->section('faq', ['title' => 'Quick Answers', 'items' => [['question' => 'A question?', 'answer' => 'An answer.']]]);
        $html = $this->html();
        $this->assertStringContainsString('<section class="section-pad" id="contact-form"><div class="container"><div class="row g-5">', $html);
        $this->assertStringContainsString('<div class="faq-row"><details><summary class="fw-bold">A question?</summary>', $html);
        $this->assertStringNotContainsString('kl-contact', $html);
    }
    public function test_hero_contact_methods_render_as_labels_not_figures(): void
    {
        $this->section('hero', ['layout' => 'cinematic', 'title' => 'Let’s Build|*a Stronger*|*Sports Community.*', 'image' => '/images/home/hero-player.jpg',
            'button_label' => 'Send Us a Message', 'button_url' => '#contact-form', 'secondary_button_label' => 'Book a Court', 'secondary_button_url' => '',
            'items' => [['value' => 'Talk to Us', 'title' => 'Get in touch with our team', 'icon' => 'phone'], ['value' => 'Book a Court', 'title' => 'Ask about court availability', 'icon' => 'calendar']]]);
        $html = $this->html();
        $this->assertStringContainsString('kl-hero__features kl-hero__features--figures kl-hero__features--labels', $html);
        $this->assertStringContainsString('<span class="kl-hero__figure"><strong>Talk to Us</strong> <span>Get in touch with our team</span></span>', $html);
        $this->assertMatchesRegularExpression('#href="\#contact-form">\s*Send Us a Message#', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(e(route('page.show', ['slug' => 'contact', 'topic' => 'Court Booking']).'#contact-form'), '#').'"\s*>\s*Book a Court#', $html);
    }
    public function test_numeric_hero_figures_keep_the_figure_style(): void
    {
        $this->section('hero', ['layout' => 'cinematic', 'title' => 'Academy', 'image' => '/images/home/hero-player.jpg', 'items' => [['value' => '500+', 'title' => 'Members', 'icon' => 'users']]]);
        $html = $this->html();
        $this->assertStringContainsString('kl-hero__features--figures', $html);
        $this->assertStringNotContainsString('kl-hero__features--labels', $html);
    }
    public function test_the_still_have_questions_cta_keeps_contact_and_court_booking_actions(): void
    {
        $this->section('cta', ['layout' => 'journey', 'tone' => 'green', 'title' => 'Still Have Questions?', 'button_label' => 'Contact Us', 'button_url' => '#contact-form',
            'secondary_button_label' => 'Book a Court', 'secondary_button_url' => '/contact?topic=Court%20Booking#contact-form', 'image' => '/images/home/stats-shuttlecock.jpg']);
        $html = $this->html();
        $this->assertStringContainsString('kl-cta--journey', $html);
        $this->assertMatchesRegularExpression('#href="\#contact-form">Contact Us#', $html);
        $this->assertMatchesRegularExpression('#href="/contact\?topic=Court%20Booking\#contact-form"\s*>Book a Court#', $html);
    }
    public function test_editors_can_choose_the_contact_layouts_and_link_enquiry_cards(): void
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]);
        foreach ([['contact_form', 'split', 'Form panel beside the location details (Contact design)'], ['map_embed', 'beside', 'Shown beside the contact form (Contact design)'],
                  ['faq', 'beside', 'Shown beside the quick enquiry cards (Contact design)'], ['feature_list', 'enquiries', 'Quick enquiry cards beside the FAQ section (Contact design)']] as [$type, $layout, $label]) {
            $section = $this->section($type, ['title' => 'Section']);
            $this->actingAs($editor)->get(route('admin.pages.sections.edit', [$this->page, $section]))->assertOk()->assertSee($label);
            $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $section]), ['title' => 'Edited', 'is_visible' => '1', 'layout' => $layout])->assertSessionHasNoErrors();
            $this->assertSame([$layout, 'Edited'], [$section->fresh()->data['layout'], $section->fresh()->data['title']]);
            $this->actingAs($editor)->put(route('admin.pages.sections.update', [$this->page, $section]), ['layout' => 'bands'])->assertSessionHasErrors('layout');
        }
        $cards = PageSection::where('type', 'feature_list')->sole();
        $url = route('admin.pages.sections.update', [$this->page, $cards]);
        $this->actingAs($editor)->put($url, ['title' => 'Help', 'is_visible' => '1', 'layout' => 'enquiries', 'items_json' => json_encode([['title' => 'Court|Booking', 'icon' => 'calendar', 'url' => '/contact?topic=Court%20Booking#contact-form']])])->assertSessionHasNoErrors();
        $this->assertSame('/contact?topic=Court%20Booking#contact-form', $cards->fresh()->data['items'][0]['url']);
        $this->actingAs($editor)->put($url, ['title' => 'Help', 'is_visible' => '1', 'items_json' => json_encode([['title' => 'Bad', 'url' => 'javascript:alert(1)']])])->assertSessionHasErrors('items_json');
    }
}

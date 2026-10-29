<?php
namespace Tests\Feature;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
class SectionUpdateTest extends TestCase
{
    use RefreshDatabase;
    private Page $page;
    /** A value for every field any section form can edit, plus keys no form edits. */
    private const FULL = [
        'title' => 'Title', 'eyebrow' => 'Eyebrow', 'subtitle' => 'Subtitle', 'body' => 'Body text', 'image' => 'media/2026/10/a.jpg',
        'mobile_image' => 'media/2026/10/b.jpg', 'image_position' => 'top', 'image_alt' => 'Alt text', 'button_label' => 'Go',
        'button_url' => '/contact', 'map_url' => 'https://www.google.com/maps/embed?pb=1', 'secondary_button_label' => 'More',
        'secondary_button_url' => '/about', 'tone' => 'green', 'heading_accent' => 'gold', 'script_text' => 'Script', 'values_text' => 'A|B',
        'video_url' => 'https://example.com/video', 'video_label' => 'Watch', 'custom_key' => 'kept', 'items' => [['title' => 'One', 'icon' => 'coach']],
    ];
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        $this->page = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'draft']);
    }
    private function section(string $type, array $data = [], array $attributes = []): PageSection
    {
        if (isset(\App\Support\SectionDefaults::LAYOUTS[$type])) $data += ['layout' => array_key_last(\App\Support\SectionDefaults::LAYOUTS[$type])];
        return PageSection::create($attributes + ['page_id' => $this->page->id, 'type' => $type, 'admin_label' => 'Label', 'sort_order' => 1, 'is_visible' => true, 'data' => $data + self::FULL + ($type === 'stats' ? ['demo_content' => true] : [])]);
    }
    /** Exactly what a browser submits for the section's real edit form, untouched. */
    private function formFields(PageSection $section): array
    {
        $html = $this->get(route('admin.pages.sections.edit', [$this->page, $section]))->assertOk()->getContent();
        $doc = new DOMDocument(); libxml_use_internal_errors(true); $doc->loadHTML($html); libxml_clear_errors();
        $xpath = new DOMXPath($doc);
        $form = null;
        foreach ($xpath->query('//form') as $candidate) if ($candidate->getAttribute('action') === route('admin.pages.sections.update', [$this->page, $section])) $form = $candidate;
        $this->assertNotNull($form, 'section edit form not found');
        $fields = [];
        foreach ($xpath->query('.//input|.//textarea|.//select', $form) as $el) {
            $name = $el->getAttribute('name');
            if ($name === '' || $el->hasAttribute('disabled')) continue;
            if ($el->nodeName === 'textarea') { $fields[$name] = $el->textContent; continue; }
            if ($el->nodeName === 'select') {
                foreach ($xpath->query('.//option', $el) as $i => $option) if ($i === 0 || $option->hasAttribute('selected')) $fields[$name] = $option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent;
                continue;
            }
            $type = strtolower($el->getAttribute('type'));
            if (in_array($type, ['checkbox', 'radio'], true)) { if ($el->hasAttribute('checked')) $fields[$name] = $el->getAttribute('value') ?: 'on'; }
            elseif (! in_array($type, ['submit', 'button', 'file', 'reset'], true)) $fields[$name] = $el->getAttribute('value');
        }
        return $fields;
    }
    private function save(PageSection $section, array $fields)
    {
        return $this->from(route('admin.pages.sections.edit', [$this->page, $section]))->put(route('admin.pages.sections.update', [$this->page, $section]), $fields);
    }

    public static function sectionTypes(): array
    {
        return array_combine(PageSection::TYPES, array_map(fn ($type) => [$type], PageSection::TYPES));
    }
    #[DataProvider('sectionTypes')]
    public function test_saving_the_unchanged_form_keeps_every_stored_value(string $type): void
    {
        $section = $this->section($type);
        $before = $section->fresh();
        $this->save($section, $this->formFields($section))->assertSessionHasNoErrors()->assertRedirect(route('admin.pages.edit', $this->page));
        $after = $section->fresh();
        $this->assertEquals($before->data, $after->data);
        $this->assertSame('Label', $after->admin_label);
        $this->assertTrue($after->is_visible);
    }
    public function test_saving_a_hero_keeps_its_tone_which_the_hero_form_does_not_show(): void
    {
        $hero = $this->section('hero');
        $fields = $this->formFields($hero);
        $this->assertArrayNotHasKey('tone', $fields);
        $this->save($hero, ['title' => 'New hero title'] + $fields)->assertSessionHasNoErrors();
        $this->assertSame('green', $hero->fresh()->data['tone']);
        $this->assertSame('New hero title', $hero->fresh()->data['title']);
    }
    public function test_editing_one_field_leaves_the_others_untouched(): void
    {
        $cta = $this->section('cta');
        $this->save($cta, ['subtitle' => 'Changed subtitle'] + $this->formFields($cta))->assertSessionHasNoErrors();
        $this->assertEquals(['subtitle' => 'Changed subtitle'] + $cta->data, $cta->fresh()->data);
    }
    public function test_explicitly_emptied_fields_are_saved_as_empty(): void
    {
        $hero = $this->section('hero');
        // An emptied text box arrives as "" (Laravel turns it into null); it must clear the value, not keep the old one.
        $this->save($hero, ['title' => '', 'script_text' => '', 'image_position' => ''] + $this->formFields($hero))->assertSessionHasNoErrors();
        $data = $hero->fresh()->data;
        $this->assertSame('', $data['title']);
        $this->assertSame('', $data['script_text']); // stored, so the homepage default no longer applies
        $this->assertSame('center', $data['image_position']); // the position field's own empty value
        $this->assertSame('green', $data['tone']);
    }
    public function test_visibility_and_demo_checkboxes_still_toggle(): void
    {
        $stats = $this->section('stats');
        $fields = $this->formFields($stats);
        $this->assertArrayHasKey('is_visible', $fields);
        $this->assertArrayHasKey('demo_content', $fields);
        unset($fields['is_visible'], $fields['demo_content']); // unchecked boxes are not submitted
        $this->save($stats, $fields)->assertSessionHasNoErrors();
        $this->assertFalse($stats->fresh()->is_visible);
        $this->assertFalse($stats->fresh()->data['demo_content']);
        $this->save($stats, $this->formFields($stats->fresh()) + ['is_visible' => '1', 'demo_content' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($stats->fresh()->is_visible);
        $this->assertTrue($stats->fresh()->data['demo_content']);
    }
    public function test_nested_items_can_be_replaced_and_cleared(): void
    {
        $grid = $this->section('card_grid');
        // A newly entered media path must be an image in the library, so this one is backed by a real asset.
        \Illuminate\Support\Facades\Storage::fake('public')->put('media/2026/10/c.jpg', 'x');
        \App\Models\MediaAsset::create(['disk' => 'public', 'path' => 'media/2026/10/c.jpg', 'original_name' => 'c.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1]);
        $items = [['title' => 'Coaching', 'icon' => 'coach', 'color' => '#123456'], ['title' => 'Courts', 'image' => 'media/2026/10/c.jpg']];
        $this->save($grid, ['items_json' => json_encode($items)] + $this->formFields($grid))->assertSessionHasNoErrors();
        $this->assertSame($items, $grid->fresh()->data['items']);
        $this->save($grid, ['items_json' => ''] + $this->formFields($grid->fresh()))->assertSessionHasNoErrors();
        $this->assertSame([], $grid->fresh()->data['items']);
        $this->assertSame('green', $grid->fresh()->data['tone']);
    }
    public static function invalidSubmissions(): array
    {
        return [
            'unknown tone' => [['tone' => 'purple']],
            'unsafe button link' => [['button_url' => 'javascript:alert(1)']],
            'malformed items' => [['items_json' => '{not json']],
            'unsafe nested link' => [['items_json' => '[{"url":"javascript:alert(1)"}]']],
            'too-long title' => [['title' => str_repeat('x', 241)]],
        ];
    }
    #[DataProvider('invalidSubmissions')]
    public function test_invalid_submissions_change_nothing(array $bad): void
    {
        $cta = $this->section('cta', ['items' => [['title' => 'Kept']]]);
        $before = $cta->fresh();
        $revisions = PageRevision::count();
        $this->save($cta, ['subtitle' => 'Should not be saved', 'is_visible' => null] + $bad + $this->formFields($cta))->assertSessionHasErrors();
        $after = $cta->fresh();
        $this->assertEquals($before->data, $after->data);
        $this->assertSame($before->is_visible, $after->is_visible);
        $this->assertSame($before->admin_label, $after->admin_label);
        $this->assertSame($revisions, PageRevision::count());
    }
    public function test_a_custom_image_position_survives_saving_and_can_still_be_changed(): void
    {
        // Seeded design values such as the homepage hero's "44% center" are not one of the dropdown presets.
        $hero = $this->section('hero', ['image_position' => '44% center']);
        $fields = $this->formFields($hero);
        $this->assertSame('44% center', $fields['image_position']);
        $this->save($hero, $fields)->assertSessionHasNoErrors();
        $this->assertSame('44% center', $hero->fresh()->data['image_position']);
        $this->save($hero, ['image_position' => 'top'] + $fields)->assertSessionHasNoErrors();
        $this->assertSame('top', $hero->fresh()->data['image_position']);
        // Only the value already stored is accepted; a new arbitrary value is not.
        $this->save($hero, ['image_position' => '10% 10%'] + $this->formFields($hero->fresh()))->assertSessionHasErrors('image_position');
        $this->assertSame('top', $hero->fresh()->data['image_position']);
    }
    public function test_a_cta_without_a_stored_tone_keeps_the_tone_it_is_shown_with(): void
    {
        // The CTA template renders a missing tone as green, so the form must not save it as light.
        $data = self::FULL; unset($data['tone']);
        $cta = PageSection::create(['page_id' => $this->page->id, 'type' => 'cta', 'sort_order' => 1, 'is_visible' => true, 'data' => $data]);
        $this->save($cta, $this->formFields($cta))->assertSessionHasNoErrors();
        $this->assertSame('green', $cta->fresh()->data['tone']);
    }
    public function test_a_successful_save_records_one_revision_of_the_previous_state(): void
    {
        $hero = $this->section('hero');
        $this->save($hero, ['title' => 'Revised'] + $this->formFields($hero))->assertSessionHasNoErrors();
        $revisions = PageRevision::where('page_id', $this->page->id)->get();
        $this->assertCount(1, $revisions);
        $this->assertSame('Before editing section', $revisions[0]->revision_label);
        $this->assertSame('Title', $revisions[0]->snapshot['sections'][0]['data']['title']);
        $this->assertSame('green', $revisions[0]->snapshot['sections'][0]['data']['tone']);
    }
}

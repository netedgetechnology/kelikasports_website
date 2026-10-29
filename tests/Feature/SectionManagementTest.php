<?php
namespace Tests\Feature;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/** Adding, duplicating, deleting and reordering page sections. (Editing a section is covered by SectionUpdateTest.) */
class SectionManagementTest extends TestCase
{
    use RefreshDatabase;
    private Page $page;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'editor', 'is_active' => true]));
        $this->page = Page::create(['title' => 'Programs', 'slug' => 'programs', 'status' => 'draft', 'robots_directive' => 'index,follow', 'template' => 'standard']);
    }
    private function section(string $type, int $order, array $data = [], ?string $label = null): PageSection
    {
        return PageSection::create(['page_id' => $this->page->id, 'type' => $type, 'admin_label' => $label, 'sort_order' => $order, 'is_visible' => true, 'data' => $data ?: ['title' => ucfirst($type)]]);
    }
    private function order(): array
    {
        return $this->page->sections()->orderBy('sort_order')->get()->map(fn ($s) => $s->admin_label ?? $s->type)->all();
    }

    public function test_every_section_type_can_be_added_with_its_defaults_at_the_end(): void
    {
        foreach (PageSection::TYPES as $i => $type) {
            $this->post(route('admin.pages.sections.store', $this->page), ['type' => $type, 'admin_label' => "Added {$type}"])->assertSessionHasNoErrors();
            $section = PageSection::latest('id')->first();
            $this->assertSame([$type, "Added {$type}", $i + 1, true], [$section->type, $section->admin_label, $section->sort_order, $section->is_visible]);
            $this->assertIsArray($section->data);
            $this->get(route('admin.pages.sections.edit', [$this->page, $section]))->assertOk();
        }
        $this->assertSame(count(PageSection::TYPES), PageRevision::where('revision_label', 'Before adding section')->count());
    }
    public function test_unknown_types_and_overlong_labels_are_rejected(): void
    {
        $this->post(route('admin.pages.sections.store', $this->page), ['type' => 'carousel'])->assertSessionHasErrors('type');
        $this->post(route('admin.pages.sections.store', $this->page), ['type' => 'faq', 'admin_label' => str_repeat('x', 121)])->assertSessionHasErrors('admin_label');
        $this->assertSame(0, PageSection::count());
    }
    public function test_duplicating_copies_the_content_to_the_end_and_records_a_revision(): void
    {
        $faq = $this->section('faq', 1, ['title' => 'Questions', 'items' => [['question' => 'When?', 'answer' => 'Daily']]], 'FAQ block');
        $this->section('cta', 2);
        $this->post(route('admin.pages.sections.duplicate', [$this->page, $faq]))->assertSessionHas('success', 'Section duplicated.');
        $copy = PageSection::latest('id')->first();
        $this->assertNotSame($faq->id, $copy->id);
        $this->assertSame(['faq', 'FAQ block copy', 3, $faq->data], [$copy->type, $copy->admin_label, $copy->sort_order, $copy->data]);
        $this->assertSame('Before duplicating section', PageRevision::latest('id')->value('revision_label'));
        $unlabelled = $this->section('split', 4, [], null);
        $this->post(route('admin.pages.sections.duplicate', [$this->page, $unlabelled]));
        $this->assertSame('Split copy', PageSection::latest('id')->value('admin_label'));
    }
    public function test_deleting_removes_one_section_renumbers_the_rest_and_records_a_revision(): void
    {
        $this->section('hero', 1, [], 'Hero');
        $middle = $this->section('faq', 2, [], 'FAQ');
        $this->section('cta', 3, [], 'CTA');
        $this->delete(route('admin.pages.sections.destroy', [$this->page, $middle]))->assertSessionHas('success', 'Section removed.');
        $this->assertNull($middle->fresh());
        $this->assertSame(['Hero', 'CTA'], $this->order());
        $this->assertSame([1, 2], $this->page->sections()->orderBy('sort_order')->pluck('sort_order')->all());
        $this->assertSame(3, count(PageRevision::latest('id')->first()->snapshot['sections']), 'the revision holds the section that was removed');
    }
    public function test_the_hero_of_a_live_page_cannot_be_deleted(): void
    {
        $hero = $this->section('hero', 1, ['title' => 'Programs', 'image' => '/images/kelika-wordmark.png'], 'Hero');
        $this->page->update(['status' => 'published', 'published_at' => now()->subDay()]);
        $this->delete(route('admin.pages.sections.destroy', [$this->page, $hero]))->assertSessionHasErrors('publish');
        $this->assertNotNull($hero->fresh());
    }
    public function test_sections_move_up_and_down_and_stay_put_at_the_ends(): void
    {
        $a = $this->section('hero', 1, [], 'A');
        $b = $this->section('faq', 2, [], 'B');
        $c = $this->section('cta', 3, [], 'C');
        $this->post(route('admin.pages.sections.move', [$this->page, $c, 'up']))->assertSessionHas('success', 'Section order updated.');
        $this->assertSame(['A', 'C', 'B'], $this->order());
        $this->post(route('admin.pages.sections.move', [$this->page, $a, 'down']));
        $this->assertSame(['C', 'A', 'B'], $this->order());
        $this->post(route('admin.pages.sections.move', [$this->page, $c, 'up']))->assertSessionHas('success', 'Section is already at that end of the page.');
        $this->post(route('admin.pages.sections.move', [$this->page, $b, 'down']))->assertSessionHas('success', 'Section is already at that end of the page.');
        $this->assertSame(['C', 'A', 'B'], $this->order());
        $this->post('/admin/pages/'.$this->page->id.'/sections/'.$a->id.'/move/sideways')->assertNotFound();
    }
    public function test_sections_of_another_page_are_out_of_reach(): void
    {
        $other = Page::create(['title' => 'Other', 'slug' => 'other', 'status' => 'draft', 'robots_directive' => 'index,follow', 'template' => 'standard']);
        $foreign = PageSection::create(['page_id' => $other->id, 'type' => 'faq', 'sort_order' => 1, 'is_visible' => true, 'data' => []]);
        $this->get(route('admin.pages.sections.edit', [$this->page, $foreign]))->assertNotFound();
        $this->post(route('admin.pages.sections.duplicate', [$this->page, $foreign]))->assertNotFound();
        $this->post(route('admin.pages.sections.move', [$this->page, $foreign, 'up']))->assertNotFound();
        $this->delete(route('admin.pages.sections.destroy', [$this->page, $foreign]))->assertNotFound();
        $this->assertSame(1, PageSection::count());
    }
    public function test_hidden_sections_are_left_out_of_the_public_page(): void
    {
        $this->section('hero', 1, ['title' => 'Programs', 'image' => '/images/kelika-wordmark.png'], 'Hero');
        $this->section('rich_text', 2, ['title' => 'Shown block', 'body' => 'Visible'], 'Shown');
        PageSection::create(['page_id' => $this->page->id, 'type' => 'rich_text', 'sort_order' => 3, 'is_visible' => false, 'data' => ['title' => 'Hidden block', 'body' => 'Hidden']]);
        $this->post(route('admin.pages.publish', $this->page))->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get('/programs')->assertOk()->assertSee('Shown block')->assertDontSee('Hidden block');
    }
}

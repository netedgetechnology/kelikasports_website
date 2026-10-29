<?php
namespace Tests\Feature;
use App\Models\ContentItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/** Public search: partial, literal matching, and only content visitors may see. */
class PublicSearchTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->page('Badminton Courts', 'badminton', ['seo_description' => 'Six indoor courts']);
        $this->page('Fees: 100% refundable', 'fees');
        $this->page('Code SUMMER_CAMP', 'codes');
        $this->page('Plain 1000 things', 'plain');
        $this->item('sport', 'Table Tennis');
        $this->item('program', 'Junior_Squad');
    }
    private function page(string $title, string $slug, array $attributes = []): Page
    {
        return Page::create($attributes + ['title' => $title, 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subDay(), 'robots_directive' => 'index,follow', 'template' => 'standard', 'show_in_menu' => true]);
    }
    private function item(string $type, string $title, array $attributes = []): ContentItem
    {
        return ContentItem::create($attributes + ['type' => $type, 'title' => $title, 'slug' => str($title)->slug(), 'status' => 'published', 'data' => []]);
    }
    /** Titles in the "Pages" and "Content" result lists. */
    private function results(string $q): array
    {
        $html = $this->get('/search?q='.rawurlencode($q))->assertOk()->getContent();
        preg_match_all('#<a class="fw-bold" href="[^"]*">([^<]+)</a>#', $html, $pages);
        preg_match_all('#<h4 class="h6 fw-bold">([^<]+)</h4>#', $html, $items);
        return ['pages' => array_map('html_entity_decode', $pages[1]), 'items' => array_map('html_entity_decode', $items[1])];
    }

    public function test_titles_and_descriptions_are_matched_partially_and_case_insensitively(): void
    {
        $this->assertSame(['Badminton Courts'], $this->results('badmin')['pages']);
        $this->assertSame(['Badminton Courts'], $this->results('INDOOR')['pages']);
        $this->assertSame(['Table Tennis'], $this->results('tennis')['items']);
        $this->assertSame(['pages' => [], 'items' => []], $this->results('cricket'));
    }
    public function test_percent_and_underscore_match_only_themselves(): void
    {
        $this->assertSame(['Fees: 100% refundable'], $this->results('%')['pages']);
        $this->assertSame(['Fees: 100% refundable'], $this->results('100%')['pages']);
        $this->assertSame(['Code SUMMER_CAMP'], $this->results('_')['pages']);
        $this->assertSame(['Junior_Squad'], $this->results('_')['items']);
        $this->assertSame([], $this->results('1_0')['pages'], 'an underscore is not a single-character wildcard');
        $this->assertSame(['Code SUMMER_CAMP'], $this->results('summer_camp')['pages']);
    }
    public function test_an_empty_query_shows_the_prompt_and_no_results(): void
    {
        foreach (['', '   '] as $q) {
            $this->get('/search?q='.rawurlencode($q))->assertOk()->assertSee('Enter a search term to find published pages and content.')->assertDontSee('No matching pages.');
        }
        $this->get('/search')->assertOk()->assertSee('Search the KELIKA website');
        $this->get('/search?q='.str_repeat('a', 101))->assertSessionHasErrors('q');
    }
    public function test_html_in_the_query_is_shown_as_text(): void
    {
        $response = $this->get('/search?q='.rawurlencode('<script>alert(1)</script>"'));
        $response->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;&quot;', $response->getContent());
    }
    public function test_only_live_pages_and_published_real_content_are_found(): void
    {
        $this->page('Badminton draft', 'badminton-draft', ['status' => 'draft']);
        $this->page('Badminton later', 'badminton-later', ['status' => 'scheduled', 'published_at' => now()->addDay()]);
        $this->page('Badminton archived', 'badminton-archived')->delete();
        $this->page('Badminton due', 'badminton-due', ['status' => 'scheduled', 'published_at' => now()->subMinute()]);
        $this->item('sport', 'Badminton draft item', ['status' => 'draft']);
        $this->item('sport', 'Badminton archived item')->delete();
        $this->item('sport', 'Badminton demo item', ['data' => ['demo' => true]]);
        $this->item('testimonial', 'Badminton testimonial');
        $this->item('sport', 'Badminton Club');
        $results = $this->results('badminton');
        sort($results['pages']);
        $this->assertSame(['Badminton Courts', 'Badminton due'], $results['pages']);
        $this->assertSame(['Badminton Club'], $results['items']);
        // Even where demo content is shown on pages, search never lists it.
        config(['kelika.demo_content.public' => true, 'kelika.demo_content.hosts' => ['localhost']]);
        $this->assertSame(['Badminton Club'], $this->results('badminton')['items']);
    }
    public function test_search_results_are_never_indexed(): void
    {
        $this->assertSame('noindex, follow', $this->get('/search?q=badminton')->headers->get('X-Robots-Tag'));
    }
}

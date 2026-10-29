<?php
namespace Tests\Feature;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class SectionImagePickerTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    private Page $page;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Media and public_path() live in a throwaway folder outside the project.
        $this->root = sys_get_temp_dir().'/kelika-picker-test-'.bin2hex(random_bytes(6));
        foreach (['public', 'private', 'web/images', 'web/build'] as $dir) mkdir("{$this->root}/{$dir}", 0755, true);
        file_put_contents("{$this->root}/web/build/manifest.json", '{}');
        config(['filesystems.disks.public.root' => "{$this->root}/public", 'filesystems.disks.local.root' => "{$this->root}/private"]);
        Storage::forgetDisk(['public', 'local']);
        $this->app->usePublicPath("{$this->root}/web");
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
        $this->page = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'draft']);
    }
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
        parent::tearDown();
    }
    /** A real upload through the media library, so WebP copies and dimensions exist. */
    private function uploaded(string $name = 'court.jpg'): MediaAsset
    {
        $im = imagecreatetruecolor(1300, 866);
        for ($y = 0; $y < 866; $y += 6) for ($x = 0; $x < 1300; $x += 6) imagefilledrectangle($im, $x, $y, $x + 5, $y + 5, imagecolorallocate($im, ($x + $y) % 256, $x % 256, $y % 256));
        imagejpeg($im, $file = $this->root.'/'.bin2hex(random_bytes(4)).'.jpg', 90);
        $this->post(route('admin.media.store'), ['file' => new UploadedFile($file, $name, null, null, true), 'alt_text' => 'Courts at dusk'])->assertSessionHasNoErrors();
        return MediaAsset::latest('id')->firstOrFail();
    }
    /** A record without the upload pipeline; its file exists only when asked for. */
    private function record(string $name, string $mime = 'image/jpeg', bool $withFile = true): MediaAsset
    {
        $path = 'media/2026/10/'.$name;
        if ($withFile) Storage::disk('public')->put($path, 'x');
        return MediaAsset::create(['disk' => 'public', 'path' => $path, 'original_name' => $name, 'mime_type' => $mime, 'size_bytes' => 1]);
    }
    private function section(string $type, array $data = []): PageSection
    {
        // A value for every field a form can submit, so saving an unchanged form changes nothing.
        if (isset(\App\Support\SectionDefaults::LAYOUTS[$type])) $data += ['layout' => array_key_first(\App\Support\SectionDefaults::LAYOUTS[$type])];
        if ($type === 'stats') $data += ['demo_content' => true];
        return PageSection::create(['page_id' => $this->page->id, 'type' => $type, 'admin_label' => 'Label', 'sort_order' => 1, 'is_visible' => true, 'data' => $data + [
            'title' => 'Title', 'eyebrow' => 'Eyebrow', 'subtitle' => 'Kept subtitle', 'body' => 'Body', 'image' => '', 'mobile_image' => '', 'image_position' => 'center',
            'image_alt' => 'Alt', 'button_label' => 'Go', 'button_url' => '/contact', 'secondary_button_label' => 'More', 'secondary_button_url' => '/about',
            'map_url' => '', 'tone' => 'green', 'heading_accent' => 'gold', 'script_text' => 'Script', 'values_text' => 'A|B', 'video_url' => '', 'video_label' => '',
            'items' => [], 'custom_key' => 'kept',
        ]]);
    }
    /** Exactly what a browser submits for the section's edit form, untouched. */
    private function formFields(PageSection $section): array
    {
        $html = $this->get(route('admin.pages.sections.edit', [$this->page, $section]))->assertOk()->getContent();
        $doc = new DOMDocument(); libxml_use_internal_errors(true); $doc->loadHTML($html); libxml_clear_errors();
        $xpath = new DOMXPath($doc);
        $form = $xpath->query('//form[@id="section-editor-form"]')->item(0);
        $this->assertNotNull($form);
        $fields = [];
        foreach ($xpath->query('.//input|.//textarea|.//select', $form) as $el) {
            $name = $el->getAttribute('name');
            if ($name === '') continue;
            if ($el->nodeName === 'textarea') { $fields[$name] = $el->textContent; continue; }
            if ($el->nodeName === 'select') {
                foreach ($xpath->query('.//option', $el) as $i => $option) if ($i === 0 || $option->hasAttribute('selected')) $fields[$name] = $option->getAttribute('value');
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

    // The picker's list
    public function test_the_picker_lists_only_supported_images_whose_files_exist_with_small_thumbnails(): void
    {
        $photo = $this->uploaded();
        $png = $this->record('logo.png', 'image/png');
        $this->record('brochure.pdf', 'application/pdf');
        $this->record('gone.jpg', 'image/jpeg', false);
        $this->record('vector.svg', 'image/svg+xml');
        $this->record('trashed.jpg')->delete();

        $json = $this->getJson(route('admin.media.picker'))->assertOk()->json();
        $this->assertEqualsCanonicalizing([$photo->id, $png->id], array_column($json['data'], 'id'));
        $this->assertNull($json['next_page']);
        $option = collect($json['data'])->firstWhere('id', $photo->id);
        $this->assertSame($photo->path, $option['path'], 'the original path is what gets stored');
        $this->assertSame(['court.jpg', 'Courts at dusk', 1300, 866, 'JPG'], [$option['name'], $option['alt'], $option['width'], $option['height'], $option['type']]);
        $this->assertMatchesRegularExpression('/w400-[0-9a-f]{12}\.webp$/', $option['thumb'], 'the thumbnail is the smallest WebP copy, not the full-size original');
        $this->assertStringContainsString('800w', $option['thumb_srcset']);

        $this->assertSame([$png->id], array_column($this->getJson(route('admin.media.picker', ['q' => 'logo']))->json('data'), 'id'));
    }
    public function test_the_picker_list_uses_a_fixed_number_of_queries_and_paginates(): void
    {
        foreach (range(1, 30) as $i) $this->record("photo-{$i}.jpg");
        DB::enableQueryLog();
        $json = $this->getJson(route('admin.media.picker'))->assertOk()->json();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(24, $json['data']);
        $this->assertSame(2, $json['next_page']);
        $this->assertLessThanOrEqual(3, $queries, 'one query for the page, whatever its size (plus the session user)');
        $this->assertCount(6, $this->getJson(route('admin.media.picker', ['page' => 2]))->json('data'));
    }
    public function test_the_editor_shows_the_current_image_and_the_picker_only_where_images_are_edited(): void
    {
        $asset = $this->uploaded();
        $split = $this->section('split', ['image' => $asset->path, 'mobile_image' => 'media/2026/09/removed.jpg']);
        $html = $this->get(route('admin.pages.sections.edit', [$this->page, $split]))->assertOk()->getContent();
        $this->assertStringContainsString('id="media-picker"', $html);
        $this->assertStringContainsString('name="image_media_id"', $html);
        $this->assertStringContainsString('name="mobile_image_media_id"', $html);
        $this->assertStringContainsString('court.jpg', $html);
        $this->assertStringContainsString('1300 × 866', $html);
        $this->assertMatchesRegularExpression('/data-pick-thumb>\s*<img src="[^"]*w400-[0-9a-f]{12}\.webp"/', $html, 'a small WebP copy, not the original');
        $this->assertStringContainsString('Not an available media library image', $html, 'a path without a usable asset is flagged');

        $faq = $this->section('faq');
        $html = $this->get(route('admin.pages.sections.edit', [$this->page, $faq]))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="media-picker"', $html);
        $this->assertStringNotContainsString('image_media_id', $html);
    }

    // Selecting
    public function test_selecting_an_image_stores_its_original_path_and_keeps_everything_else(): void
    {
        $desktop = $this->uploaded('desktop.jpg');
        $mobile = $this->uploaded('mobile.jpg');
        $hero = $this->section('hero', ['image' => 'media/2026/09/old.jpg', 'image_position' => '44% center', 'items' => [['title' => 'One', 'icon' => 'coach']]]);
        $before = $hero->data;

        $fields = ['image_media_id' => (string) $desktop->id, 'mobile_image_media_id' => (string) $mobile->id] + $this->formFields($hero);
        $this->save($hero, $fields)->assertSessionHasNoErrors()->assertRedirect(route('admin.pages.edit', $this->page));
        $data = $hero->fresh()->data;
        $this->assertSame($desktop->path, $data['image']);
        $this->assertSame($mobile->path, $data['mobile_image']);
        $this->assertSame('44% center', $data['image_position'], 'a fine-tuned focal point survives');
        $this->assertSame('Alt', $data['image_alt']);
        $unrelated = fn ($d) => collect($d)->except(['image', 'mobile_image'])->all();
        $this->assertEquals($unrelated($before), $unrelated($data), 'no other section data changes');
    }
    public function test_a_picked_image_wins_over_the_path_box(): void
    {
        $asset = $this->uploaded();
        $cta = $this->section('cta');
        $this->save($cta, ['image' => 'https://example.com/typed.jpg', 'image_media_id' => (string) $asset->id] + $this->formFields($cta))->assertSessionHasNoErrors();
        $this->assertSame($asset->path, $cta->fresh()->data['image']);
    }
    public static function unavailableSelections(): array
    {
        return [
            'nonexistent id' => [fn () => 999999],
            'document' => [fn ($t) => $t->record('brochure.pdf', 'application/pdf')->id],
            'unsupported image type' => [fn ($t) => $t->record('vector.svg', 'image/svg+xml')->id],
            'missing file' => [fn ($t) => $t->record('gone.jpg', 'image/jpeg', false)->id],
            'deleted asset' => [fn ($t) => tap($t->record('trashed.jpg'))->delete()->id],
            'not a number' => [fn () => 'media/2026/10/x.jpg'],
        ];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('unavailableSelections')]
    public function test_unavailable_selections_are_rejected_and_nothing_changes(\Closure $id): void
    {
        $split = $this->section('split', ['image' => '/images/home/existing.jpg']);
        $before = $split->data;
        $fields = $this->formFields($split);
        $this->save($split, ['image_media_id' => (string) $id($this), 'title' => 'Changed'] + $fields)->assertSessionHasErrors();
        $this->save($split, ['mobile_image_media_id' => (string) $id($this)] + $fields)->assertSessionHasErrors();
        $this->assertEquals($before, $split->fresh()->data);
    }
    public function test_a_selection_is_refused_for_sections_without_image_fields(): void
    {
        $asset = $this->uploaded();
        $faq = $this->section('faq');
        $this->save($faq, ['image_media_id' => (string) $asset->id] + $this->formFields($faq))->assertSessionHasErrors('image_media_id');
        $this->assertSame('', $faq->fresh()->data['image']);
    }
    public function test_a_typed_media_path_must_also_be_an_available_image(): void
    {
        $asset = $this->uploaded();
        $pdf = $this->record('brochure.pdf', 'application/pdf');
        $split = $this->section('split');
        $fields = $this->formFields($split);
        $this->save($split, ['image' => 'media/2026/10/does-not-exist.jpg'] + $fields)->assertSessionHasErrors('image');
        $this->save($split, ['mobile_image' => $pdf->path] + $fields)->assertSessionHasErrors('mobile_image');
        $this->assertSame('', $split->fresh()->data['image']);
        $this->save($split, ['image' => $asset->path] + $fields)->assertSessionHasNoErrors();
        $this->assertSame($asset->path, $split->fresh()->data['image']);
    }

    // Clearing and preserving
    public function test_optional_images_can_be_cleared(): void
    {
        $asset = $this->uploaded();
        $split = $this->section('split', ['image' => $asset->path, 'mobile_image' => $asset->path, 'image_position' => 'top']);
        $this->save($split, ['image' => '', 'mobile_image' => '', 'image_media_id' => '', 'mobile_image_media_id' => ''] + $this->formFields($split))->assertSessionHasNoErrors();
        $data = $split->fresh()->data;
        $this->assertSame(['', '', 'top', 'kept'], [$data['image'], $data['mobile_image'], $data['image_position'], $data['custom_key']]);
    }
    public function test_a_published_hero_still_cannot_lose_its_image(): void
    {
        $asset = $this->uploaded();
        $this->page->update(['status' => 'published', 'published_at' => now()->subDay()]);
        $hero = $this->section('hero', ['image' => $asset->path]);
        $html = $this->get(route('admin.pages.sections.edit', [$this->page, $hero]))->getContent();
        $this->assertMatchesRegularExpression('/data-optional="0"[\s\S]*name="image_media_id"/', $html, 'no Clear button for a required image');
        $this->save($hero, ['image' => ''] + $this->formFields($hero))->assertSessionHasErrors('publish');
        $this->assertSame($asset->path, $hero->fresh()->data['image']);
    }
    public function test_existing_custom_paths_are_kept_when_the_form_is_saved_unchanged(): void
    {
        $values = [
            'image' => '/images/home/kelika-hero.jpg',
            'mobile_image' => 'https://images.unsplash.com/photo-1?auto=format&fit=crop&w=1000&q=80',
        ];
        foreach (['hero', 'split', 'cta', 'stats'] as $type) {
            $section = $this->section($type, $values + ['image_position' => '30% 40%']);
            $before = $section->fresh()->data;
            $fields = $this->formFields($section);
            $this->assertSame('', $fields['image_media_id']);
            $this->save($section, $fields)->assertSessionHasNoErrors();
            $this->assertEquals($before, $section->fresh()->data, "{$type} keeps its custom paths");
        }
        // A media path stored before this change is kept even though no asset backs it.
        $legacy = $this->section('cta', ['image' => 'media/2026/09/legacy.jpg', 'items' => [['image' => 'media/2026/09/legacy-item.jpg']]]);
        $this->save($legacy, $this->formFields($legacy))->assertSessionHasNoErrors();
        $this->assertSame('media/2026/09/legacy.jpg', $legacy->fresh()->data['image']);
        // New custom paths and URLs are still accepted.
        $this->save($legacy, ['image' => '/images/home/other.jpg', 'mobile_image' => 'https://example.com/m.jpg'] + $this->formFields($legacy))->assertSessionHasNoErrors();
        $this->assertSame(['/images/home/other.jpg', 'https://example.com/m.jpg'], [$legacy->fresh()->data['image'], $legacy->fresh()->data['mobile_image']]);
    }

    // Item images
    public function test_item_images_accept_library_images_and_reject_unavailable_media(): void
    {
        $asset = $this->uploaded();
        $pdf = $this->record('brochure.pdf', 'application/pdf');
        $grid = $this->section('card_grid', ['items' => [['title' => 'Old', 'image' => 'media/2026/09/legacy.jpg', 'image_position' => 'top']]]);
        $fields = $this->formFields($grid);
        $items = fn (string $image) => json_encode([['title' => 'Old', 'image' => 'media/2026/09/legacy.jpg', 'image_position' => 'top'], ['title' => 'New', 'image' => $image, 'mobile_image' => '']]);

        $this->save($grid, ['items_json' => $items('media/2026/10/missing.jpg')] + $fields)->assertSessionHasErrors('items_json');
        $this->save($grid, ['items_json' => $items($pdf->path)] + $fields)->assertSessionHasErrors('items_json');
        $this->assertCount(1, $grid->fresh()->data['items']);

        $this->save($grid, ['items_json' => $items($asset->path)] + $fields)->assertSessionHasNoErrors();
        $saved = $grid->fresh()->data['items'];
        $this->assertSame(['media/2026/09/legacy.jpg', 'top'], [$saved[0]['image'], $saved[0]['image_position']], 'the existing item keeps its path and focal point');
        $this->assertSame($asset->path, $saved[1]['image']);
    }

    // Public pages
    public function test_public_pages_render_picked_images_with_webp_copies_and_art_direction(): void
    {
        $desktop = $this->uploaded('desktop.jpg');
        $mobile = $this->uploaded('mobile.jpg');
        $this->page->update(['status' => 'published', 'published_at' => now()->subDay()]);
        $hero = $this->section('hero', ['image' => '/images/home/placeholder.jpg', 'image_position' => '44% center', 'image_alt' => 'Players on court']);
        $this->save($hero, ['image_media_id' => (string) $desktop->id, 'mobile_image_media_id' => (string) $mobile->id] + $this->formFields($hero))->assertSessionHasNoErrors();

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('src="'.$desktop->url.'"', $html, 'the original is the fallback');
        $this->assertMatchesRegularExpression('/<source media="\(max-width: 767px\)" type="image\/webp" srcset="[^"]*'.preg_quote($mobile->variantsDirectory(), '/').'\/w400-/', $html, 'the mobile image is used below 768px, as WebP');
        $this->assertMatchesRegularExpression('/<source type="image\/webp" srcset="[^"]*'.preg_quote($desktop->variantsDirectory(), '/').'\/w400-/', $html);
        $this->assertStringContainsString('--kl-hero-pos: 44% center', $html);
        $this->assertStringContainsString('alt="Players on court"', $html);
    }
}

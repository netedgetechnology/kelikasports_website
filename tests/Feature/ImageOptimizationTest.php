<?php
namespace Tests\Feature;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use App\Support\ImageOptimizer;
use App\Support\Media;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
class ImageOptimizationTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Public media, private history and public_path() all point at a throwaway folder outside the project.
        $this->root = sys_get_temp_dir().'/kelika-image-test-'.bin2hex(random_bytes(6));
        foreach (['public', 'private', 'web/images', 'web/build'] as $dir) mkdir("{$this->root}/{$dir}", 0755, true);
        file_put_contents("{$this->root}/web/build/manifest.json", '{}'); // the layout then uses Vite, which withoutVite() stubs
        config(['filesystems.disks.public.root' => "{$this->root}/public", 'filesystems.disks.local.root' => "{$this->root}/private"]);
        Storage::forgetDisk(['public', 'local']);
        $this->app->usePublicPath("{$this->root}/web");
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
    }
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
        parent::tearDown();
    }
    /** A photo-like JPEG (gradient plus noise) so WebP sizes behave realistically. */
    private function photo(int $width, int $height, string $type = 'jpeg'): string
    {
        $im = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y += 4) for ($x = 0; $x < $width; $x += 4) {
            $c = imagecolorallocate($im, ($x * 255 / $width + mt_rand(0, 40)) % 256, ($y * 255 / $height + mt_rand(0, 40)) % 256, mt_rand(60, 120));
            imagefilledrectangle($im, $x, $y, $x + 3, $y + 3, $c);
        }
        $path = $this->root.'/src-'.bin2hex(random_bytes(4)).'.'.($type === 'jpeg' ? 'jpg' : $type);
        match ($type) { 'jpeg' => imagejpeg($im, $path, 92), 'png' => imagepng($im, $path), 'webp' => imagewebp($im, $path, 95) };
        return $path;
    }
    private function upload(string $path, string $name = 'court.jpg'): UploadedFile
    {
        return new UploadedFile($path, $name, null, null, true);
    }
    private function store(UploadedFile $file)
    {
        return $this->from(route('admin.media.index'))->post(route('admin.media.store'), ['file' => $file]);
    }
    private function variantFiles(MediaAsset $asset): array
    {
        $files = array_map('basename', glob(Storage::disk('public')->path($asset->variantsDirectory()).'/*') ?: []);
        sort($files);
        return $files;
    }

    // Uploads
    public function test_a_valid_upload_keeps_the_original_and_creates_webp_copies(): void
    {
        $source = $this->photo(1300, 866);
        $this->store($this->upload($source))->assertSessionHasNoErrors()->assertSessionHas('success');
        $asset = MediaAsset::firstOrFail();
        $this->assertSame(sha1_file($source), sha1_file(Storage::disk('public')->path($asset->path)), 'original stored unchanged');
        $this->assertMatchesRegularExpression('#^media/\d{4}/\d{2}/[0-9a-f-]{36}\.jpg$#', $asset->path);
        $optimization = $asset->metadata['optimization'];
        $this->assertSame(ImageOptimizer::STATUS_OPTIMIZED, $optimization['status']);
        $widths = array_column($optimization['variants'], 'width');
        $this->assertSame([400, 800, 1200], array_values(array_filter($widths, fn ($w) => $w < 1300)));
        $this->assertLessThanOrEqual(1300, max($widths), 'never upscaled');
        foreach ($optimization['variants'] as $variant) {
            $info = getimagesize(Storage::disk('public')->path($asset->variantsDirectory().'/'.$variant['file']));
            $this->assertSame(IMAGETYPE_WEBP, $info[2]);
            $this->assertSame([$variant['width'], (int) round(866 * $variant['width'] / 1300)], [$info[0], $info[1]], 'aspect ratio kept');
        }
        $this->assertSame([1300, 866], [$asset->metadata['width'], $asset->metadata['height']]);
        $this->assertStringStartsWith('Optimised', $asset->optimizationLabel());
    }
    public function test_webp_and_transparent_png_uploads_are_supported_and_keep_transparency(): void
    {
        $this->store($this->upload($this->photo(900, 600, 'webp'), 'a.webp'))->assertSessionHasNoErrors();
        $this->assertSame(ImageOptimizer::STATUS_OPTIMIZED, MediaAsset::where('mime_type', 'image/webp')->first()->metadata['optimization']['status']);

        $im = imagecreatetruecolor(1000, 400); imagealphablending($im, false); imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagefilledrectangle($im, 300, 100, 700, 300, imagecolorallocate($im, 6, 79, 67));
        imagepng($im, $logo = $this->root.'/logo.png');
        $this->store($this->upload($logo, 'logo.png'))->assertSessionHasNoErrors();
        $asset = MediaAsset::where('mime_type', 'image/png')->firstOrFail();
        $variant = $asset->metadata['optimization']['variants'][0];
        $copy = imagecreatefromwebp(Storage::disk('public')->path($asset->variantsDirectory().'/'.$variant['file']));
        $this->assertSame(127, (imagecolorat($copy, 2, 2) >> 24) & 0x7F, 'transparent corner stays transparent');
        $this->assertLessThan(20, (imagecolorat($copy, (int) ($variant['width'] / 2), (int) ($variant['height'] / 2)) >> 24) & 0x7F, 'opaque centre stays opaque');
    }
    public function test_disguised_and_invalid_files_are_rejected(): void
    {
        file_put_contents($php = $this->root.'/shell.jpg', "<?php system(\$_GET['c']); ?>");
        $this->store($this->upload($php, 'shell.jpg'))->assertSessionHasErrors('file');
        file_put_contents($text = $this->root.'/notes.png', 'just text');
        $this->store($this->upload($text, 'notes.png'))->assertSessionHasErrors('file');
        file_put_contents($broken = $this->root.'/broken.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\0", 64));
        $this->store($this->upload($broken, 'broken.jpg'))->assertSessionHasErrors('file');
        $this->assertSame(0, MediaAsset::count());
        $this->assertSame([], glob(Storage::disk('public')->path('media').'/*/*/*') ?: [], 'nothing stored');
    }
    public function test_existing_size_and_dimension_limits_still_apply(): void
    {
        $this->store(UploadedFile::fake()->image('big.jpg', 100, 100)->size(\App\Http\Controllers\Admin\MediaController::maxUploadKb() + 1))->assertSessionHasErrors('file');
        $wide = imagecreatetruecolor(6001, 10); imagejpeg($wide, $path = $this->root.'/wide.jpg');
        $this->store($this->upload($path, 'wide.jpg'))->assertSessionHasErrors('file');
        $this->assertSame(0, MediaAsset::count());
    }
    public function test_images_above_the_processing_limit_are_kept_but_clearly_not_optimised(): void
    {
        config(['kelika.images.max_pixels' => 100_000]);
        $this->store($this->upload($this->photo(500, 400)))->assertSessionHasErrors('optimization')->assertSessionHas('success');
        $asset = MediaAsset::firstOrFail();
        $this->assertSame(ImageOptimizer::STATUS_TOO_LARGE, $asset->metadata['optimization']['status']);
        $this->assertSame([], $asset->metadata['optimization']['variants']);
        $this->assertSame([], $this->variantFiles($asset));
        $this->assertStringStartsWith('Not optimised', $asset->optimizationLabel());
        $this->get(route('admin.media.index'))->assertSee('Not optimised: too large');
    }
    public function test_a_processing_failure_keeps_the_original_reports_it_and_leaves_no_partial_copies(): void
    {
        Exceptions::fake();
        $source = $this->photo(1300, 866);
        // Block the 800px copy: a folder already sits where that file must go, so the run fails after writing 400px.
        $key = substr(sha1(sha1_file($source).'|'.config('kelika.images.version').'|'.config('kelika.images.quality.photo')), 0, 12);
        $dir = $this->root.'/out';
        mkdir("$dir/w800-$key.webp", 0755, true);
        $result = ImageOptimizer::process($source, $dir);
        $this->assertSame(ImageOptimizer::STATUS_FAILED, $result['status']);
        $this->assertSame([], $result['variants']);
        $this->assertFileDoesNotExist("$dir/w400-$key.webp", 'copies written before the failure are removed');
        $this->assertSame([], glob("$dir/.tmp-*") ?: [], 'no temporary files left');
        $this->assertSame(sha1_file($source), $result['source_sha1']);

        // Through the upload: the original and record are kept, the failure is reported and never marked optimised.
        config(['kelika.images.widths' => [-1]]); // an impossible width makes GD fail
        $this->store($this->upload($this->photo(600, 400)))->assertSessionHasErrors('optimization')->assertSessionHas('success');
        $asset = MediaAsset::firstOrFail();
        $this->assertFileExists(Storage::disk('public')->path($asset->path));
        $this->assertSame(ImageOptimizer::STATUS_FAILED, $asset->metadata['optimization']['status']);
        $this->assertSame('Not optimised: processing failed (original is used)', $asset->optimizationLabel());
        Exceptions::assertReported(RuntimeException::class);
    }
    public function test_a_failed_save_after_processing_leaves_no_file_copies_or_record(): void
    {
        Exceptions::fake();
        MediaAsset::creating(fn () => throw new RuntimeException('Simulated database failure'));
        $this->store($this->upload($this->photo(900, 600)))->assertSessionHasErrors('file');
        $this->assertSame(0, MediaAsset::count());
        $this->assertSame([], (new Filesystem)->allFiles(Storage::disk('public')->path('')), 'original and copies removed');
    }
    public function test_reprocessing_rewrites_the_same_copies_and_new_settings_replace_old_ones(): void
    {
        $this->store($this->upload($this->photo(1300, 866)))->assertSessionHasNoErrors();
        $asset = MediaAsset::firstOrFail();
        $first = $this->variantFiles($asset);
        $asset->update(['metadata' => $asset->optimizedMetadata()]);
        $this->assertSame($first, $this->variantFiles($asset), 'same names, no duplicates');
        config(['kelika.images.version' => 2]);
        $this->artisan('kelika:images', ['--only' => 'media', '--write' => true])->assertSuccessful();
        $asset->refresh();
        $second = $this->variantFiles($asset);
        $this->assertCount(count($first), $second);
        $this->assertSame([], array_intersect($first, $second), 'old-setting copies are removed, not accumulated');
        $this->assertSame(2, $asset->metadata['optimization']['version']);
    }

    // Replacement
    public function test_replacing_keeps_the_record_path_and_references_and_archives_the_previous_files_privately(): void
    {
        $this->store($this->upload($old = $this->photo(1300, 866)))->assertSessionHasNoErrors();
        $asset = MediaAsset::firstOrFail();
        $page = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()->subDay()]);
        PageSection::create(['page_id' => $page->id, 'type' => 'hero', 'data' => ['image' => $asset->path, 'image_position' => '44% center']]);
        $oldVariants = $this->variantFiles($asset);

        $newSha1 = sha1_file($new = $this->photo(1600, 900)); // the upload moves the file, as a real upload does
        $this->from(route('admin.media.index'))->post(route('admin.media.replace', $asset), ['file' => $this->upload($new, 'new.jpg')])->assertSessionHasNoErrors()->assertSessionHas('success');
        $fresh = $asset->fresh();
        $this->assertSame([$asset->id, $asset->path], [$fresh->id, $fresh->path]);
        $this->assertSame($newSha1, sha1_file(Storage::disk('public')->path($asset->path)));
        $newVariants = $this->variantFiles($fresh);
        $this->assertSame([], array_intersect($oldVariants, $newVariants), 'previous copies left the public folder');

        $history = glob(Storage::disk('local')->path("media-history/{$asset->id}").'/*')[0];
        $this->assertStringStartsWith($this->root.'/private/', $history, 'history lives on the private disk');
        $this->assertSame(sha1_file($old), sha1_file($history.'/'.basename($asset->path)));
        $this->assertSame($oldVariants, array_map('basename', glob($history.'/variants/*')));
        $this->assertSame($asset->path, json_decode(file_get_contents($history.'/record.json'), true)['path']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('?v='.substr($newSha1, 0, 10), $html, 'cache-busting version of the new file');
        foreach ($newVariants as $file) $this->assertStringContainsString($file, $html);
        $this->assertStringContainsString('--kl-hero-pos: 44% center', $html, 'focal position untouched');
    }
    public function test_replacement_must_be_the_same_file_type(): void
    {
        $this->store($this->upload($this->photo(800, 600)))->assertSessionHasNoErrors();
        $asset = MediaAsset::firstOrFail();
        $before = sha1_file(Storage::disk('public')->path($asset->path));
        $this->post(route('admin.media.replace', $asset), ['file' => $this->upload($this->photo(800, 600, 'png'), 'x.png')])->assertSessionHasErrors('file');
        $this->assertSame($before, sha1_file(Storage::disk('public')->path($asset->path)));
    }
    public function test_a_failed_replacement_restores_the_previous_file_and_record(): void
    {
        Exceptions::fake();
        $this->store($this->upload($old = $this->photo(1300, 866)))->assertSessionHasNoErrors();
        $asset = MediaAsset::firstOrFail();
        $before = ['metadata' => $asset->metadata, 'size' => $asset->size_bytes, 'variants' => $this->variantFiles($asset)];
        MediaAsset::updating(fn () => throw new RuntimeException('Simulated database failure'));
        $this->from(route('admin.media.index'))->post(route('admin.media.replace', $asset), ['file' => $this->upload($this->photo(1600, 900), 'new.jpg')])->assertSessionHasErrors('file');
        $fresh = $asset->fresh();
        $this->assertSame(sha1_file($old), sha1_file(Storage::disk('public')->path($asset->path)), 'previous original is back');
        $this->assertSame($before['metadata'], $fresh->metadata);
        $this->assertSame($before['size'], $fresh->size_bytes);
        $this->assertSame($before['variants'], $this->variantFiles($fresh), 'new copies removed, previous copies kept');
        $this->assertSame([], glob(Storage::disk('local')->path("media-history/{$asset->id}").'/*') ?: [], 'no history for a replacement that did not happen');
        $this->assertSame([], glob(Storage::disk('local')->path('media-staging').'/*') ?: [], 'staged upload removed');
    }
    public function test_pdfs_upload_and_replace_as_documents_without_processing(): void
    {
        file_put_contents($pdf = $this->root.'/a.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->store($this->upload($pdf, 'brochure.pdf'))->assertSessionHasNoErrors()->assertSessionHas('success', 'Media uploaded.');
        $asset = MediaAsset::firstOrFail();
        $this->assertSame('application/pdf', $asset->mime_type);
        $this->assertArrayNotHasKey('optimization', $asset->metadata);
        $this->assertNull($asset->optimizationLabel());
        $this->assertDirectoryDoesNotExist(Storage::disk('public')->path($asset->variantsDirectory()));
        file_put_contents($pdf2 = $this->root.'/b.pdf', "%PDF-1.4\n2 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $pdf2Sha1 = sha1_file($pdf2);
        $this->post(route('admin.media.replace', $asset), ['file' => $this->upload($pdf2, 'brochure-v2.pdf')])->assertSessionHasNoErrors();
        $this->assertSame($pdf2Sha1, sha1_file(Storage::disk('public')->path($asset->path)));
        $this->assertStringEndsWith('?v='.substr($pdf2Sha1, 0, 10), $asset->fresh()->url);
    }

    // Deletion
    public function test_deleting_an_unused_image_removes_its_copies_but_not_its_history(): void
    {
        $this->store($this->upload($this->photo(1300, 866)))->assertSessionHasNoErrors();
        $asset = MediaAsset::firstOrFail();
        $this->post(route('admin.media.replace', $asset), ['file' => $this->upload($this->photo(900, 600), 'b.jpg')])->assertSessionHasNoErrors();
        $this->from(route('admin.media.index'))->delete(route('admin.media.destroy', $asset))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($asset);
        $this->assertFileDoesNotExist(Storage::disk('public')->path($asset->path));
        $this->assertDirectoryDoesNotExist(Storage::disk('public')->path($asset->variantsDirectory()));
        $this->assertDirectoryExists(Storage::disk('public')->path(dirname($asset->path)), 'the month folder is kept');
        $this->assertNotEmpty(glob(Storage::disk('local')->path("media-history/{$asset->id}").'/*'), 'history kept');
    }
    public function test_a_referenced_image_with_copies_is_still_protected_from_deletion(): void
    {
        $this->store($this->upload($this->photo(900, 600)))->assertSessionHasNoErrors();
        $asset = MediaAsset::firstOrFail();
        $page = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()->subDay()]);
        PageSection::create(['page_id' => $page->id, 'type' => 'cta', 'data' => ['image' => $asset->path]]);
        $this->from(route('admin.media.index'))->delete(route('admin.media.destroy', $asset))->assertSessionHasErrors('media');
        $this->assertNotSoftDeleted($asset);
        $this->assertNotEmpty($this->variantFiles($asset));
    }

    // Rendering
    public function test_pages_offer_webp_copies_with_dimensions_and_fall_back_when_copies_are_missing(): void
    {
        $this->store($this->upload($this->photo(1300, 866)))->assertSessionHasNoErrors();
        $asset = MediaAsset::firstOrFail();
        $page = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()->subDay()]);
        PageSection::create(['page_id' => $page->id, 'type' => 'cta', 'sort_order' => 1, 'data' => ['image' => $asset->path, 'image_position' => '78% center']]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertMatchesRegularExpression('/srcset="[^"]*w400-[0-9a-f]{12}\.webp 400w, [^"]*w800-[0-9a-f]{12}\.webp 800w/', $html);
        $this->assertStringContainsString('width="1300" height="866"', $html);
        $this->assertStringContainsString('object-position:78% center', $html);
        $this->assertStringContainsString('loading="lazy"', $html);

        $files = $this->variantFiles($asset);
        unlink(Storage::disk('public')->path($asset->variantsDirectory().'/'.$files[0]));
        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString($files[0], $html, 'a missing copy is left out');
        (new Filesystem)->deleteDirectory(Storage::disk('public')->path($asset->variantsDirectory()));
        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString('type="image/webp"', $html, 'no WebP source without copies');
        $this->assertStringContainsString('src="'.$asset->url.'"', $html, 'the original is the fallback');
    }
    public function test_unsplash_images_get_responsive_widths_without_changing_their_crop(): void
    {
        $srcset = Media::unsplashSrcset('https://images.unsplash.com/photo-1?auto=format&fit=crop&w=1200&h=800&q=82');
        $this->assertSame(
            'https://images.unsplash.com/photo-1?auto=format&fit=crop&w=400&h=267&q=82 400w, https://images.unsplash.com/photo-1?auto=format&fit=crop&w=800&h=533&q=82 800w, https://images.unsplash.com/photo-1?auto=format&fit=crop&w=1200&h=800&q=82 1200w',
            $srcset);
        $this->assertNull(Media::unsplashSrcset('https://images.unsplash.com/photo-1?auto=format'), 'no width, no srcset');
        $page = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()->subDay()]);
        PageSection::create(['page_id' => $page->id, 'type' => 'hero', 'data' => ['image' => 'https://images.unsplash.com/photo-2?auto=format&fit=crop&w=1800&q=85', 'mobile_image' => 'https://images.unsplash.com/photo-3?auto=format&fit=crop&w=1000&q=80']]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<source media="(max-width: 767px)" srcset="https://images.unsplash.com/photo-3?auto=format&amp;fit=crop&amp;w=400&amp;q=80 400w', $html, 'mobile art direction kept');
        $this->assertStringContainsString('photo-2?auto=format&amp;fit=crop&amp;w=1800&amp;q=85 1800w', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]*kl-hero__image[^>]*loading=/', $html, 'hero stays eager');
    }

    // Existing site images (public/images) and the command
    public function test_the_command_is_a_dry_run_by_default_writes_only_with_write_and_refuses_production(): void
    {
        mkdir(public_path('images/home'), 0755, true);
        copy($this->photo(2100, 1400), $hero = public_path('images/home/hero.jpg'));
        $original = sha1_file($hero);
        $this->artisan('kelika:images', ['--only' => 'static'])->assertSuccessful();
        $this->assertDirectoryDoesNotExist(public_path('images/optimized'), 'dry run writes nothing');

        $this->artisan('kelika:images', ['--only' => 'static', '--write' => true])->assertSuccessful();
        $manifest = json_decode(file_get_contents(Media::staticManifestPath()), true)['images']['/images/home/hero.jpg'];
        $this->assertSame(ImageOptimizer::STATUS_OPTIMIZED, $manifest['status']);
        foreach ($manifest['variants'] as $variant) $this->assertFileExists(public_path('images/optimized/home/hero/'.$variant['file']));
        $this->assertSame($original, sha1_file($hero), 'original untouched');

        $page = Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()->subDay()]);
        PageSection::create(['page_id' => $page->id, 'type' => 'hero', 'data' => ['image' => '/images/home/hero.jpg']]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('/images/optimized/home/hero/w2000-', $html);
        $this->assertStringContainsString('src="/images/home/hero.jpg"', $html);
        $this->assertStringContainsString('width="2100" height="1400"', $html);

        $this->app['env'] = 'production';
        $this->artisan('kelika:images', ['--write' => true])->assertFailed();
    }
}

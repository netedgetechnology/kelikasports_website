<?php
namespace Tests\Feature;
use App\Http\Controllers\Admin\MediaController;
use App\Models\MediaAsset;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class MediaUploadSizeTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Media lives in a throwaway folder outside the project.
        $this->root = sys_get_temp_dir().'/kelika-size-test-'.bin2hex(random_bytes(6));
        foreach (['public', 'private'] as $dir) mkdir("{$this->root}/{$dir}", 0755, true);
        config(['filesystems.disks.public.root' => "{$this->root}/public", 'filesystems.disks.local.root' => "{$this->root}/private"]);
        Storage::forgetDisk(['public', 'local']);
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'correct-horse-battery-staple', 'role' => 'administrator', 'is_active' => true]));
    }
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
        parent::tearDown();
    }
    /** A JPEG of roughly the requested weight: noise compresses poorly, so size follows the pixel count. */
    private function jpeg(int $side, int $quality = 90): string
    {
        $im = imagecreatetruecolor($side, $side);
        for ($y = 0; $y < $side; $y += 2) for ($x = 0; $x < $side; $x += 2) imagefilledrectangle($im, $x, $y, $x + 1, $y + 1, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        imagejpeg($im, $path = $this->root.'/'.bin2hex(random_bytes(4)).'.jpg', $quality);
        return $path;
    }
    private function upload(string $path, string $name)
    {
        return $this->from(route('admin.media.index'))->post(route('admin.media.store'), ['file' => new UploadedFile($path, $name, null, null, true)]);
    }
    private function storedSize(string $html, MediaAsset $asset): ?string
    {
        $doc = new DOMDocument(); libxml_use_internal_errors(true); $doc->loadHTML('<?xml encoding="utf-8"?>'.$html); libxml_clear_errors();
        $node = (new DOMXPath($doc))->query('//span[@data-stored-size="'.$asset->size_bytes.'"]')->item(0);
        return $node?->textContent;
    }

    public function test_sizes_are_formatted_like_the_upload_form(): void
    {
        // The same cases are checked against the JavaScript formatter in the development verification.
        foreach ([0 => '0 bytes', 512 => '512 bytes', 1024 => '1 KB', 250880 => '245 KB', 1048575 => '1024 KB', 1048576 => '1 MB', 2516582 => '2.4 MB', 8388608 => '8 MB', 9542041 => '9.1 MB'] as $bytes => $label) {
            $this->assertSame($label, MediaAsset::formatBytes($bytes), "{$bytes} bytes");
        }
    }
    public function test_the_upload_forms_carry_the_unchanged_limit_for_the_pre_upload_check(): void
    {
        $asset = MediaAsset::create(['disk' => 'public', 'path' => 'media/2026/10/a.jpg', 'original_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1]);
        Storage::disk('public')->put($asset->path, 'x');
        $html = $this->get(route('admin.media.index'))->assertOk()->getContent();
        $bytes = MediaController::maxUploadKb() * 1024;
        $label = MediaController::formatKb(MediaController::maxUploadKb());
        $this->assertLessThanOrEqual(8192 * 1024, $bytes, 'the CMS ceiling still applies');
        $this->assertStringContainsString('id="file" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required data-file-size="file-size-info" data-max-bytes="'.$bytes.'" data-max-label="'.$label.'"', $html);
        $this->assertStringContainsString('id="file-size-info" data-file-size-info aria-live="polite"', $html);
        $this->assertStringContainsString('data-file-size="replace-size-'.$asset->id.'" data-max-bytes="'.$bytes.'"', $html, 'replacement uploads get the same check');
        $this->assertSame(2, substr_count($html, 'data-upload-form'));
    }
    public function test_small_and_large_uploads_show_their_actual_stored_size(): void
    {
        $small = $this->jpeg(200);
        $this->upload($small, 'small.jpg')->assertSessionHasNoErrors();
        $large = $this->jpeg(650, 100);
        $this->assertGreaterThan(1048576, filesize($large), 'fixture must be over 1 MB');
        $this->assertLessThan(MediaController::maxUploadKb() * 1024, filesize($large), 'and within the upload limit');
        $this->upload($large, 'large.jpg')->assertSessionHasNoErrors();

        [$a, $b] = [MediaAsset::where('original_name', 'small.jpg')->firstOrFail(), MediaAsset::where('original_name', 'large.jpg')->firstOrFail()];
        $this->assertSame(filesize(Storage::disk('public')->path($a->path)), (int) $a->size_bytes, 'the recorded size is the stored original');
        $this->assertSame(filesize(Storage::disk('public')->path($b->path)), (int) $b->size_bytes);
        $html = $this->get(route('admin.media.index'))->getContent();
        $this->assertSame(round($a->size_bytes / 1024).' KB', $this->storedSize($html, $a));
        $this->assertSame(round($b->size_bytes / 1048576, 1).' MB', $this->storedSize($html, $b));
        $this->assertStringNotContainsString('.0 KB', $html, 'the old one-decimal KB display is gone');
        // The image picker reports the same stored size.
        $this->assertSame(round($b->size_bytes / 1048576, 1).' MB', collect($this->getJson(route('admin.media.picker'))->json('data'))->firstWhere('id', $b->id)['size']);
    }
    public function test_an_oversized_file_is_still_rejected_by_the_server(): void
    {
        $kb = MediaController::maxUploadKb();
        $this->from(route('admin.media.index'))->post(route('admin.media.store'), ['file' => UploadedFile::fake()->image('huge.jpg', 10, 10)->size($kb + 1)])
            ->assertSessionHasErrors(['file' => 'Files must be '.MediaController::formatKb($kb).' or smaller.']);
        $this->assertSame(0, MediaAsset::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
    public function test_type_and_image_validation_are_unchanged(): void
    {
        file_put_contents($fake = $this->root.'/not-an-image.jpg', 'plain text pretending to be a photo');
        $this->upload($fake, 'not-an-image.jpg')->assertSessionHasErrors('file');
        $this->from(route('admin.media.index'))->post(route('admin.media.store'), ['file' => UploadedFile::fake()->create('script.php', 1, 'application/x-php')])->assertSessionHasErrors('file');
        $this->assertSame(0, MediaAsset::count());
    }
    public function test_a_replacement_shows_the_new_stored_size(): void
    {
        $this->upload($this->jpeg(200), 'court.jpg')->assertSessionHasNoErrors();
        $asset = MediaAsset::firstOrFail();
        $before = (int) $asset->size_bytes;
        $this->from(route('admin.media.index'))->post(route('admin.media.replace', $asset), ['file' => new UploadedFile($this->jpeg(700), 'court-v2.jpg', null, null, true)])->assertSessionHasNoErrors();
        $asset->refresh();
        $this->assertNotSame($before, (int) $asset->size_bytes);
        $this->assertSame(filesize(Storage::disk('public')->path($asset->path)), (int) $asset->size_bytes);
        $this->assertSame(MediaAsset::formatBytes($asset->size_bytes), $this->storedSize($this->get(route('admin.media.index'))->getContent(), $asset));
    }
    public function test_a_missing_recorded_size_falls_back_to_the_file_and_is_omitted_when_unknown(): void
    {
        Storage::disk('public')->put('media/2026/10/old.jpg', str_repeat('x', 3000));
        $old = MediaAsset::create(['disk' => 'public', 'path' => 'media/2026/10/old.jpg', 'original_name' => 'old.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 0]);
        $gone = MediaAsset::create(['disk' => 'public', 'path' => 'media/2026/10/gone.jpg', 'original_name' => 'gone.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 0]);
        $broken = MediaAsset::create(['disk' => 'public', 'path' => 'media/2026/10/', 'original_name' => 'broken', 'mime_type' => 'image/jpeg', 'size_bytes' => 0]);
        $this->assertSame('3 KB', $old->sizeLabel());
        $this->assertNull($gone->sizeLabel());
        $this->assertNull($broken->sizeLabel(), 'a directory path never counts as a file size');
        $this->get(route('admin.media.index'))->assertOk()->assertSee('3 KB');
    }
}

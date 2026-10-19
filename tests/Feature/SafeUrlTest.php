<?php
namespace Tests\Feature;
use App\Support\SafeUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
class SafeUrlTest extends TestCase
{
    public static function safeUrls(): array { return [['/contact'],['#contact-form'],['https://example.com/booking'],['media/2026/10/image.webp']]; }
    #[DataProvider('safeUrls')]
    public function test_expected_safe_url_formats_are_accepted(string $url): void { $this->assertTrue(SafeUrl::isSafe($url, true)); }
    public function test_mail_and_telephone_links_are_allowed_for_navigation(): void { $this->assertTrue(SafeUrl::isSafe('mailto:hello@example.com')); $this->assertTrue(SafeUrl::isSafe('tel:+911234567890')); }
    public function test_mail_links_are_not_allowed_as_image_sources(): void { $this->assertFalse(SafeUrl::isSafe('mailto:hello@example.com', true)); }
    public function test_script_urls_are_rejected(): void { $this->assertFalse(SafeUrl::isSafe('javascript:alert(1)')); }
    public function test_protocol_relative_urls_are_rejected(): void { $this->assertFalse(SafeUrl::isSafe('//example.com/path')); }
    public function test_path_traversal_is_rejected_for_media(): void { $this->assertFalse(SafeUrl::isSafe('media/../../secret.txt', true)); }
}

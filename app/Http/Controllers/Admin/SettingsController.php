<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\AdminBranding;
use App\Support\Branding;
use App\Support\MediaPicker;
use App\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function edit()
    {
        $settings = Setting::query()->orderBy('group')->orderBy('key')->get()->keyBy('key');

        return view('admin.settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'site_tagline' => ['nullable', 'string', 'max:240'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'footer_logo_url' => ['nullable', 'string', 'max:2048'],
            'mobile_logo_url' => ['nullable', 'string', 'max:2048'],
            'favicon_url' => ['nullable', 'string', 'max:2048'],
            'social_image_url' => ['nullable', 'string', 'max:2048'],
            'booking_button_label' => ['required', 'string', 'max:80'],
            'font_family' => ['required', Rule::in(Branding::FONTS)],
            'button_style' => ['required', Rule::in(Branding::BUTTON_STYLES)],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'terracotta_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'gold_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'earth_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'water_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'fire_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'air_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'space_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            // Checked the way the contact form checks it before sending, so an accepted address is always notified.
            'contact_notification_email' => ['nullable', 'email:rfc,filter', 'max:190'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'opening_hours' => ['nullable', 'string', 'max:1000'],
            'map_url' => ['nullable', 'url', 'max:2048'],
            'booking_url' => ['nullable', 'url', 'max:2048'],
            // Social profiles are shown as footer links only when set, and only as web addresses.
            'instagram_url' => ['nullable', 'url:http,https', 'max:2048'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:2048'],
            'youtube_url' => ['nullable', 'url:http,https', 'max:2048'],
            'linkedin_url' => ['nullable', 'url:http,https', 'max:2048'],
            'x_url' => ['nullable', 'url:http,https', 'max:2048'],
            // Footer legal links: an existing page chosen by its address; the footer links it only while it is published.
            'terms_page' => ['nullable', 'string', Rule::exists('pages', 'slug')->whereNull('deleted_at')],
            'privacy_page' => ['nullable', 'string', Rule::exists('pages', 'slug')->whereNull('deleted_at')],
            // CMS branding: sidebar colour (empty = the original dark green) and logo (an available library image,
            // or a new upload, which goes through the media library's checks and 2 MB limit).
            'admin_sidebar_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'admin_logo_media_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                if (! MediaPicker::find($value)) $fail('Choose an available image from the media library as the CMS logo.');
            }],
            'admin_logo_upload' => MediaController::fileRules(imagesOnly: true, required: false),
            // A new public website logo (the logo_url setting), checked and stored like any library upload.
            'logo_upload' => MediaController::fileRules(imagesOnly: true, required: false),
        ], MediaController::fileMessages('admin_logo_upload') + MediaController::fileMessages('logo_upload') + [
            'logo_upload.mimes' => 'The website logo must be a JPG, PNG or WebP image.',
            'logo_upload.mimetypes' => 'The website logo must be a JPG, PNG or WebP image.',
            'admin_sidebar_color.regex' => 'The sidebar colour must be a hex colour such as #073f36.',
            'admin_logo_upload.mimes' => 'The CMS logo must be a JPG, PNG or WebP image.',
            'admin_logo_upload.mimetypes' => 'The CMS logo must be a JPG, PNG or WebP image.',
        ]);

        foreach (['logo_url', 'footer_logo_url', 'mobile_logo_url', 'favicon_url', 'social_image_url'] as $imageField) {
            if (! SafeUrl::isSafe($data[$imageField] ?? null, true)) {
                return back()
                    ->withErrors([$imageField => 'Use an uploaded media path or a valid http/https URL.'])
                    ->withInput();
            }
        }

        $websiteLogo = $this->uploadedLogo($request, 'logo_upload', 'Website logo');
        if ($websiteLogo instanceof \Illuminate\Http\RedirectResponse) return $websiteLogo;
        if ($websiteLogo !== null) $data['logo_url'] = $websiteLogo;
        $logo = $this->adminLogo($request, $data);
        if ($logo instanceof \Illuminate\Http\RedirectResponse) return $logo;
        unset($data['admin_logo_media_id'], $data['admin_logo_upload'], $data['logo_upload']);
        if ($logo !== null) $data['admin_logo_url'] = $logo;

        // Footer and phone logos that were only copies of the old website logo follow the new one ("same as the
        // website logo"), so changing the website logo changes it everywhere. A deliberately different one is kept.
        $oldWebsiteLogo = (string) (Setting::read('logo_url') ?? '');
        if (array_key_exists('logo_url', $data) && (string) $data['logo_url'] !== $oldWebsiteLogo && $oldWebsiteLogo !== '') {
            foreach (['footer_logo_url', 'mobile_logo_url'] as $key) {
                if (array_key_exists($key, $data) && (string) $data[$key] === $oldWebsiteLogo) $data[$key] = null;
            }
        }
        // The default colour is stored as empty, and unchanged CMS settings are not rewritten.
        if (strtolower((string) ($data['admin_sidebar_color'] ?? '')) === AdminBranding::DEFAULT_SIDEBAR_COLOR) $data['admin_sidebar_color'] = null;
        foreach (['admin_logo_url', 'admin_sidebar_color'] as $key) {
            if (array_key_exists($key, $data) && (Setting::read($key) ?: null) === ($data[$key] ?: null)) unset($data[$key]);
        }

        $groups = [
            'site_name' => 'general', 'site_tagline' => 'general', 'booking_button_label' => 'general',
            'font_family' => 'branding', 'button_style' => 'branding', 'logo_url' => 'branding', 'footer_logo_url' => 'branding', 'mobile_logo_url' => 'branding',
            'favicon_url' => 'branding', 'social_image_url' => 'branding', 'primary_color' => 'branding',
            'terracotta_color' => 'branding', 'gold_color' => 'branding', 'earth_color' => 'branding',
            'water_color' => 'branding', 'fire_color' => 'branding', 'air_color' => 'branding', 'space_color' => 'branding',
            'contact_phone' => 'contact', 'contact_email' => 'contact', 'contact_notification_email' => 'contact',
            'contact_address' => 'contact', 'opening_hours' => 'contact', 'map_url' => 'contact', 'booking_url' => 'general',
            'instagram_url' => 'social', 'facebook_url' => 'social', 'youtube_url' => 'social', 'linkedin_url' => 'social', 'x_url' => 'social', 'terms_page' => 'general', 'privacy_page' => 'general',
            'admin_logo_url' => 'cms', 'admin_sidebar_color' => 'cms',
        ];

        foreach ($data as $key => $value) {
            Setting::write($key, $value, $groups[$key] ?? 'general');
        }

        return back()->with('success', 'Global settings saved.');
    }

    /**
     * The CMS logo to store: a new upload (saved to the media library), the library image chosen in the list, or ''
     * for none. Null leaves the current setting alone (the form did not include the logo fields).
     */
    private function adminLogo(Request $request, array $data): string|null|\Illuminate\Http\RedirectResponse
    {
        $uploaded = $this->uploadedLogo($request, 'admin_logo_upload', 'CMS logo');
        if ($uploaded !== null) return $uploaded;
        if (! $request->exists('admin_logo_media_id')) return null;

        return filled($data['admin_logo_media_id'] ?? null) ? MediaPicker::find($data['admin_logo_media_id'])->path : '';
    }

    /** Stores an uploaded logo in the media library; its path, an error response, or null when nothing was uploaded. */
    private function uploadedLogo(Request $request, string $field, string $altText): string|null|\Illuminate\Http\RedirectResponse
    {
        if (! $request->hasFile($field)) return null;
        try {
            $asset = MediaController::storeUpload($request->file($field), ['alt_text' => $altText], $request->user()->id);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([$field => collect($exception->errors())->flatten()->first()]);
        }

        return is_string($asset) ? back()->withErrors([$field => $asset])->withInput() : $asset->path;
    }
}

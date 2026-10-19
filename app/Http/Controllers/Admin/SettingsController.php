<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'font_family' => ['required', Rule::in(['Inter', 'Arial', 'system-ui', 'Georgia'])],
            'button_style' => ['required', Rule::in(['square', 'soft', 'rounded'])],
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
            'contact_notification_email' => ['nullable', 'email', 'max:190'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'opening_hours' => ['nullable', 'string', 'max:1000'],
            'map_url' => ['nullable', 'url', 'max:2048'],
            'booking_url' => ['nullable', 'url', 'max:2048'],
            'instagram_url' => ['nullable', 'url', 'max:2048'],
            'facebook_url' => ['nullable', 'url', 'max:2048'],
            'youtube_url' => ['nullable', 'url', 'max:2048'],
            'linkedin_url' => ['nullable', 'url', 'max:2048'],
        ]);

        foreach (['logo_url', 'footer_logo_url', 'mobile_logo_url', 'favicon_url', 'social_image_url'] as $imageField) {
            if (! SafeUrl::isSafe($data[$imageField] ?? null, true)) {
                return back()
                    ->withErrors([$imageField => 'Use an uploaded media path or a valid http/https URL.'])
                    ->withInput();
            }
        }

        $groups = [
            'site_name' => 'general', 'site_tagline' => 'general', 'booking_button_label' => 'general',
            'font_family' => 'branding', 'button_style' => 'branding', 'logo_url' => 'branding', 'footer_logo_url' => 'branding', 'mobile_logo_url' => 'branding',
            'favicon_url' => 'branding', 'social_image_url' => 'branding', 'primary_color' => 'branding',
            'terracotta_color' => 'branding', 'gold_color' => 'branding', 'earth_color' => 'branding',
            'water_color' => 'branding', 'fire_color' => 'branding', 'air_color' => 'branding', 'space_color' => 'branding',
            'contact_phone' => 'contact', 'contact_email' => 'contact', 'contact_notification_email' => 'contact',
            'contact_address' => 'contact', 'opening_hours' => 'contact', 'map_url' => 'contact', 'booking_url' => 'general',
            'instagram_url' => 'social', 'facebook_url' => 'social', 'youtube_url' => 'social', 'linkedin_url' => 'social',
        ];

        foreach ($data as $key => $value) {
            Setting::write($key, $value, $groups[$key] ?? 'general');
        }

        return back()->with('success', 'Global settings saved.');
    }
}

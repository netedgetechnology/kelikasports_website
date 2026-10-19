@php($d = $section->data ?? [])
<section class="section-pad cta-section" style="background:{{ ($d['tone'] ?? 'green') === 'green' ? 'var(--kelika-green)' : (($d['tone'] ?? '') === 'gold' ? '#f7efdf' : (($d['tone'] ?? '') === 'terracotta' ? '#fbefea' : '#f5f4ef')) }};color:{{ ($d['tone'] ?? 'green') === 'green' ? '#fff' : 'var(--kelika-ink)' }}">
    <div class="container">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-7">
                <p class="eyebrow" style="color:inherit">{{ $d['eyebrow'] ?? '' }}</p>
                <h2 class="section-title">@foreach(explode('|', (string)($d['title'] ?? '')) as $line)<span class="{{ $loop->index === 1 ? 'accent-terracotta' : ($loop->index === 2 ? 'accent-gold' : '') }}">{{ $line }}</span>@if(!$loop->last)<br>@endif @endforeach</h2>
                @if(!empty($d['subtitle']))<p class="mt-3 mb-0">{{ $d['subtitle'] }}</p>@endif
                <div class="d-flex flex-wrap gap-2 mt-4">
                    @if(!empty($d['button_label']))<a class="btn {{ ($d['tone'] ?? 'green') === 'green' ? 'btn-warning' : 'btn-kelika' }} fw-bold" href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : route('page.show', ['slug' => 'contact']) }}">{{ $d['button_label'] }} →</a>@endif
                    @if(!empty($d['secondary_button_label']))<a class="btn btn-kelika-outline" href="{{ \App\Support\SafeUrl::isSafe($d['secondary_button_url'] ?? null) && !empty($d['secondary_button_url']) ? $d['secondary_button_url'] : (($siteSettings['booking_url'] ?? '') ?: route('page.show', ['slug' => 'contact'])) }}" @if(!empty($siteSettings['booking_url']) && empty($d['secondary_button_url'])) target="_blank" rel="noopener noreferrer" @endif>{{ $d['secondary_button_label'] }} →</a>@endif
                </div>
            </div>
            <div class="col-lg-5">
                @if(!empty($d['image']))<picture>@if(!empty($d['mobile_image']))<source media="(max-width: 767px)" srcset="{{ str_starts_with($d['mobile_image'], 'http') || str_starts_with($d['mobile_image'], '/') ? $d['mobile_image'] : Storage::disk('public')->url($d['mobile_image']) }}">@endif<img class="cta-image" src="{{ str_starts_with($d['image'], 'http') || str_starts_with($d['image'], '/') ? $d['image'] : Storage::disk('public')->url($d['image']) }}" alt="{{ $d['image_alt'] ?? '' }}" style="object-position:{{ $d['image_position'] ?? 'center' }}" loading="lazy"></picture>@elseif($preview ?? false)<div class="cta-image image-placeholder">Add the approved CTA image</div>@endif
            </div>
        </div>
    </div>
</section>

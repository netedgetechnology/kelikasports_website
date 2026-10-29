@php
    $d = \App\Support\SectionDefaults::apply($section);
    $tone = in_array($d['tone'] ?? 'green', ['light', 'green', 'terracotta', 'gold', 'soft'], true) ? ($d['tone'] ?? 'green') : 'green';
    $ctaImage = \App\Support\Media::url($d['image'] ?? null);
    $ctaMobileImage = \App\Support\Media::url($d['mobile_image'] ?? null);
    $scriptLines = \App\Support\SectionDefaults::lines($d['script_text'] ?? '');
    $ctaLayout = \App\Support\SectionDefaults::layout($section);
@endphp
<section class="kl-cta kl-cta--{{ $tone }} {{ $ctaImage ? 'kl-cta--image' : '' }}@if ($ctaLayout === 'program') kl-cta--program @elseif ($ctaLayout === 'journey') kl-cta--journey @endif">
    @if ($ctaImage)
        <x-picture picture-class="kl-cta__media" :image="$d['image'] ?? null" :mobile="$d['mobile_image'] ?? null" :alt="$d['image_alt'] ?? ''"
                   :img-style="'object-position:'.($d['image_position'] ?? 'center')" :dimensions="true" />
    @elseif ($preview ?? false)
        <div class="kl-cta__media image-placeholder">Add the approved CTA image</div>
    @endif
    <div class="container">
        <div class="kl-cta__inner">
            <div class="kl-cta__copy">
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-cta__title'])
                @if (!empty($d['subtitle']))<p class="kl-cta__text">{{ $d['subtitle'] }}</p>@endif
                <div class="kl-actions">
                    @if (!empty($d['button_label']))
                        <a class="btn {{ $tone === 'green' ? 'btn-kelika-gold' : 'btn-kelika' }}" href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : route('page.show', ['slug' => 'contact']) }}">{{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a>
                    @endif
                    @if (!empty($d['secondary_button_label']))
                        <a class="btn btn-kelika-outline" href="{{ \App\Support\SafeUrl::isSafe($d['secondary_button_url'] ?? null) && !empty($d['secondary_button_url']) ? $d['secondary_button_url'] : (($siteSettings['booking_url'] ?? '') ?: route('page.show', ['slug' => 'contact'])) }}" @if (!empty($siteSettings['booking_url']) && empty($d['secondary_button_url'])) target="_blank" rel="noopener noreferrer" @endif>{{ $d['secondary_button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a>
                    @endif
                </div>
            </div>
            @if ($scriptLines)
                <p class="kl-script kl-cta__script">
                    @foreach ($scriptLines as $line){{ $line }}@if (!$loop->last)<br>@endif @endforeach
                </p>
            @endif
        </div>
    </div>
</section>

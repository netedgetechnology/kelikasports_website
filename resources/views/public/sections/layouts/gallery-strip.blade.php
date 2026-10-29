{{-- Gallery, "strip" layout (Events design, "Moments that Inspire"): heading, intro text and an optional button in one row,
     then one row of five photos. Images marked "Demo content" (not approved KELIKA photographs) appear only in CMS
     preview and development demo mode; other visitors see neutral tiles until approved images are added. The button
     shows only with a URL that is not this page. --}}
@php
    $stripItems = array_values(array_filter($d['items'] ?? [], fn ($item) => is_array($item) && trim((string) ($item['image'] ?? '')) !== ''));
    $imagesAreDemo = ! empty($d['demo_content']);
    $showImages = $stripItems && (! $imagesAreDemo || ($preview ?? false) || ($demoMode ?? false));
    $galleryUrl = \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url'])
        && trim((string) parse_url((string) $d['button_url'], PHP_URL_PATH), '/') !== trim(request()->path(), '/') ? $d['button_url'] : null;
@endphp
<section class="kl-section kl-gallery-strip" id="{{ \Illuminate\Support\Str::slug(($d['eyebrow'] ?? '') ?: 'gallery') }}">
    <div class="container">
        <div class="kl-split-head kl-split-head--three">
            <div>
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
            </div>
            @if (!empty($d['subtitle']))<p class="kl-split-head__text">{{ $d['subtitle'] }}</p>@endif
            @if ($galleryUrl && !empty($d['button_label']))
                <a class="btn btn-kelika-outline" href="{{ $galleryUrl }}">{{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a>
            @endif
        </div>

        @if ($showImages && $imagesAreDemo && ($preview ?? false))
            @include('public.partials.preview-note', ['message' => 'Demo images — not approved KELIKA photographs. They appear only in CMS preview and development demo mode; untick “Demo content” once approved photos are in place.'])
        @elseif (! $stripItems && ($preview ?? false))
            @include('public.partials.preview-note', ['message' => 'No gallery images yet — visitors see neutral tiles. Add images in this section’s items.'])
        @endif

        <ul class="kl-gallery-strip__list">
            @if ($showImages)
                @foreach (array_slice($stripItems, 0, 5) as $item)
                    <li class="kl-gallery-strip__tile">
                        <x-picture :image="$item['image']" :mobile="($item['mobile_image'] ?? '') ?: null" :alt="$item['image_alt'] ?? ''"
                                   :img-style="'object-position:'.($item['image_position'] ?? 'center')" sizes="(min-width: 992px) 20vw, 50vw" mobile-sizes="50vw" />
                        @if (!empty($item['caption']))<span class="kl-gallery-strip__caption">{{ $item['caption'] }}</span>@endif
                    </li>
                @endforeach
            @else
                @for ($i = 0; $i < 5; $i++)
                    <li class="kl-gallery-strip__tile kl-gallery-strip__tile--empty" aria-hidden="true">{!! \App\Support\Icon::svg('camera', 'kl-icon kl-icon--lg') !!}</li>
                @endfor
            @endif
        </ul>
        @unless ($showImages)
            <p class="kl-gallery-strip__note">Photos from KELIKA events will be shared here.</p>
        @endunless
    </div>
</section>

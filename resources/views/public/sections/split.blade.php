@php
    $d = $section->data ?? [];
    $layout = \App\Support\SectionDefaults::layout($section);
    $splitImage = \App\Support\Media::url($d['image'] ?? null);
    $splitMobileImage = \App\Support\Media::url($d['mobile_image'] ?? null);
    $amenities = \App\Support\SectionDefaults::isFacilities($section) ? ($facilities ?? collect()) : collect();
    $videoUrl = \App\Support\SafeUrl::isSafe($d['video_url'] ?? null) ? ($d['video_url'] ?? '') : '';
    // Without an image (or video) the public page shows the copy on its own instead of an empty image box.
    $showMedia = $splitImage || $videoUrl || ($preview ?? false);
@endphp

@if (in_array($layout, ['program', 'courts', 'story_left'], true))
@php($showMedia = $splitImage || ($preview ?? false))
@include('public.sections.layouts.split-'.($layout === 'courts' ? 'courts' : 'program'))
@else
<section class="kl-section kl-split kl-split--{{ $layout }} {{ $amenities->isNotEmpty() ? 'kl-split--facilities' : '' }} {{ $showMedia ? '' : 'kl-split--no-media' }}"
         id="{{ \Illuminate\Support\Str::slug($d['eyebrow'] ?? $d['title'] ?? 'split') }}">
    @if ($showMedia)
    <div class="kl-split__media">
        @if ($splitImage)
            <x-picture :image="$d['image'] ?? null" :mobile="$d['mobile_image'] ?? null" :alt="$d['image_alt'] ?? ''"
                       :img-style="'object-position: '.($d['image_position'] ?? 'center')" sizes="(min-width: 992px) 65vw, 100vw" :dimensions="true" />
        @elseif ($preview ?? false)
            <div class="image-placeholder" aria-label="Image to be selected in CMS">Image to be selected in CMS</div>
        @endif

        @if ($videoUrl)
            <a class="kl-play" href="{{ $videoUrl }}" target="_blank" rel="noopener noreferrer">
                <span class="kl-play__button">{!! \App\Support\Icon::svg('play', 'kl-icon') !!}</span>
                <span>{{ $d['video_label'] ?: 'Watch video' }}</span>
            </a>
        @endif
    </div>
    @endif

    @if ($amenities->isNotEmpty())
        @include('public.partials.decor-botanical')
    @endif

    <div class="kl-split__copy">
        @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
        @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section)])

        @if (!empty($d['subtitle']))
            <p class="kl-lead mt-3">{{ $d['subtitle'] }}</p>
        @endif

        @if (!empty($d['body']))
            <p class="kl-muted mt-3">{!! nl2br(e($d['body'])) !!}</p>
        @endif

        @if ($amenities->isNotEmpty())
            <ul class="kl-amenities">
                @foreach ($amenities as $amenity)
                    <li>
                        {!! \App\Support\Icon::render($amenity->data['icon'] ?? null, $amenity->title, 'kl-icon kl-icon--lg') ?: \App\Support\Icon::svg('check', 'kl-icon kl-icon--lg') !!}
                        <span>{{ ($amenity->data['short_label'] ?? '') ?: $amenity->title }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (!empty($d['button_label']))
            <a class="btn btn-kelika mt-4"
               href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : route('page.show', ['slug' => 'contact']) }}">
                {{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
            </a>
        @endif
    </div>
</section>
@endif

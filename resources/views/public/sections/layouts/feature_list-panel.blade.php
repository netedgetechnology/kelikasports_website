{{-- Feature list, "panel" layout (Academy design, "About Kelika Academy"): intro copy and button, a photo, and the
     features (e.g. Mission / Vision / Values) on a green panel at the right edge. Without an image the panel sits beside
     the copy. --}}
@php
    $aboutImage = \App\Support\Media::url($d['image'] ?? null);
    $showMedia = $aboutImage || ($preview ?? false);
@endphp
<section @class(['kl-section', 'kl-about', 'kl-about--no-media' => ! $showMedia]) id="{{ \Illuminate\Support\Str::slug(($d['eyebrow'] ?? '') ?: 'about') }}">
    <div class="kl-about__copy">
        @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
        @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
        @if (!empty($d['body']))<p class="kl-about__text">{!! nl2br(e($d['body'])) !!}</p>@endif
        @if (!empty($d['button_label']))
            <a class="btn btn-kelika" href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : route('page.show', ['slug' => 'about']) }}">
                {{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
            </a>
        @endif
    </div>

    @if ($showMedia)
        <div class="kl-about__media">
            @if ($aboutImage)
                <x-picture :image="$d['image'] ?? null" :mobile="$d['mobile_image'] ?? null" :alt="$d['image_alt'] ?? ''"
                           :img-style="'object-position:'.($d['image_position'] ?? 'center')" sizes="(min-width: 992px) 36vw, 100vw" :dimensions="true" />
            @else
                <div class="image-placeholder">Select an image in the CMS</div>
            @endif
            <svg class="kl-about__leaves" viewBox="0 0 120 260" width="120" height="260" aria-hidden="true" focusable="false">
                <path d="M58 6C12 40 8 104 46 150 70 104 82 52 58 6Z" fill="#5f8f68" opacity=".75"/>
                <path d="M58 6C52 60 48 104 46 150" fill="none" stroke="#fff" stroke-width="1" opacity=".5"/>
                <path d="M96 104C42 122 22 170 34 226 74 204 100 158 96 104Z" fill="#7aa77d" opacity=".6"/>
                <path d="M30 170C-6 196-8 236 14 258 40 236 46 200 30 170Z" fill="#d9a85f" opacity=".75"/>
            </svg>
        </div>
    @endif

    @if (!empty($d['items']))
        <ul class="kl-about__panel">
            @foreach ($d['items'] as $item)
                <li class="kl-about__item">
                    <span class="kl-about__icon">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'] ?? '', 'kl-icon kl-icon--xl') ?: \App\Support\Icon::svg('check', 'kl-icon kl-icon--xl') !!}</span>
                    <div>
                        <h3 class="kl-about__title">{{ $item['title'] ?? '' }}</h3>
                        @if (!empty($item['description']))<p class="kl-about__desc">{{ $item['description'] }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>

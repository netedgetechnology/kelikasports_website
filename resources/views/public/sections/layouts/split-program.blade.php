{{-- Split, "program" layout (Programs design, featured Kids Program): copy with two buttons, a large photo fading into
     the copy, and the section's items as a highlights panel at the right edge. An eyebrow with a | shows its second part
     as a quieter label, e.g. "KIDS PROGRAM|AGES 5 – 12". Without an image the panel sits beside the copy. The "story_left"
     layout (About design) uses this template with the photo on the left. --}}
@php
    $eyebrowParts = \App\Support\SectionDefaults::lines($d['eyebrow'] ?? '');
    $highlights = array_values(array_filter($d['items'] ?? [], fn ($item) => is_array($item) && trim((string) ($item['title'] ?? '')) !== ''));
    $panelColors = ['#c43d45', '#1f8a52', '#c98a33', '#6c45b8', '#d4652e'];
    $contactUrl = route('page.show', ['slug' => 'contact']);
@endphp
<section @class(['kl-section', 'kl-feature-program', 'kl-feature-program--no-media' => ! $showMedia, 'kl-feature-program--no-panel' => ! $highlights, 'kl-feature-program--reverse' => ($layout ?? '') === 'story_left'])
         id="{{ \Illuminate\Support\Str::slug(($eyebrowParts[0] ?? '') ?: ($d['title'] ?? 'featured-program')) }}">
    <div class="kl-feature-program__copy">
        @if ($eyebrowParts)
            <p class="kl-kicker kl-feature-program__kicker">
                <span>{{ $eyebrowParts[0] }}</span>
                @foreach (array_slice($eyebrowParts, 1) as $part)<span class="kl-feature-program__meta">{{ $part }}</span>@endforeach
            </p>
        @endif
        @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title kl-feature-program__title'])
        @if (!empty($d['body']))<p class="kl-feature-program__text">{!! nl2br(e($d['body'])) !!}</p>@endif
        @if (!empty($d['button_label']) || !empty($d['secondary_button_label']))
            <div class="kl-actions">
                @if (!empty($d['button_label']))
                    <a class="btn btn-kelika" href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : $contactUrl }}">
                        {{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                    </a>
                @endif
                @if (!empty($d['secondary_button_label']))
                    <a class="btn btn-kelika-outline" href="{{ \App\Support\SafeUrl::isSafe($d['secondary_button_url'] ?? null) && !empty($d['secondary_button_url']) ? $d['secondary_button_url'] : $contactUrl }}">
                        {{ $d['secondary_button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                    </a>
                @endif
            </div>
        @endif
    </div>

    @if ($showMedia)
        <div class="kl-feature-program__media">
            @if ($splitImage)
                <x-picture :image="$d['image'] ?? null" :mobile="$d['mobile_image'] ?? null" :alt="$d['image_alt'] ?? ''"
                           :img-style="'object-position:'.($d['image_position'] ?? 'center')" sizes="(min-width: 992px) 40vw, 100vw" :dimensions="true" />
            @else
                <div class="image-placeholder">Select an image in the CMS</div>
            @endif
        </div>
    @endif

    @if ($highlights)
        <ul class="kl-feature-program__panel">
            @foreach ($highlights as $item)
                @php($itemColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : $panelColors[$loop->index % count($panelColors)])
                <li class="kl-feature-program__item" style="--kl-item: {{ $itemColor }}">
                    <span class="kl-feature-program__icon">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'], 'kl-icon kl-icon--lg') ?: \App\Support\Icon::svg('check', 'kl-icon kl-icon--lg') !!}</span>
                    <div>
                        <h3 class="kl-feature-program__item-title">{{ $item['title'] }}</h3>
                        @if (!empty($item['description']))<p class="kl-feature-program__item-text">{{ $item['description'] }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>

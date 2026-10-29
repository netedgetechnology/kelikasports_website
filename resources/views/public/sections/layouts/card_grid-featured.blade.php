{{-- Card grid, "featured" layout (Academy design, "Our facilities"): heading, intro text and button in one row, then the
     section image large beside a 2 × 2 grid of cards. A card without an image (its own, or the facility/program/sport
     record with the same title) shows its icon instead. Cards marked "demo": true (unverified) appear only in CMS
     preview and development demo mode. --}}
@php
    $featuredImage = \App\Support\Media::url($d['image'] ?? null);
    $featuredCards = array_values(array_filter($d['items'] ?? [], fn ($item) => is_array($item) && (empty($item['demo']) || ($preview ?? false) || ($demoMode ?? false))));
@endphp
<section class="kl-section kl-showcase" id="{{ $sectionId }}">
    <div class="container">
        <div class="kl-split-head kl-split-head--three">
            <div>
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
            </div>
            @if (!empty($d['subtitle']))<p class="kl-split-head__text">{{ $d['subtitle'] }}</p>@endif
            @if (!empty($d['button_label']))
                <a class="btn btn-kelika-outline" href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : route('page.show', ['slug' => 'facilities']) }}">
                    {{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                </a>
            @endif
        </div>
    </div>

    <div @class(['kl-showcase__grid', 'kl-showcase__grid--no-feature' => ! $featuredImage && ! ($preview ?? false)])>
        @if ($featuredImage)
            <div class="kl-showcase__feature">
                <x-picture :image="$d['image'] ?? null" :mobile="$d['mobile_image'] ?? null" :alt="$d['image_alt'] ?? ''"
                           :img-style="'object-position:'.($d['image_position'] ?? 'center')" sizes="(min-width: 992px) 60vw, 100vw" :dimensions="true" />
            </div>
        @elseif ($preview ?? false)
            <div class="kl-showcase__feature"><div class="image-placeholder">Select the featured image in CMS</div></div>
        @endif

        <div class="kl-showcase__cards">
            @foreach ($featuredCards as $item)
                @php
                    $record = $records->get(strtolower(trim((string) ($item['title'] ?? ''))));
                    $recordData = $record?->data ?? [];
                    $usesOwnImage = !empty($item['image']);
                    $cardImage = $usesOwnImage ? $item['image'] : ($recordData['image'] ?? null);
                    $cardUrl = \App\Support\SafeUrl::isSafe($item['url'] ?? null) && !empty($item['url']) ? $item['url'] : null;
                @endphp
                <article class="kl-facility">
                    <div class="kl-facility__media">
                        @if (\App\Support\Media::url($cardImage))
                            <x-picture :image="$cardImage" :mobile="$usesOwnImage ? ($item['mobile_image'] ?? null) : ($recordData['mobile_image'] ?? null)"
                                       :alt="$usesOwnImage ? ($item['image_alt'] ?? '') : ($recordData['image_alt'] ?? '')"
                                       :img-style="'object-position:'.(($usesOwnImage ? ($item['image_position'] ?? null) : ($recordData['image_position'] ?? null)) ?: 'center')"
                                       sizes="(min-width: 992px) 20vw, (min-width: 576px) 50vw, 100vw" mobile-sizes="(min-width: 576px) 50vw, 100vw" />
                        @else
                            <span class="kl-facility__icon">{!! \App\Support\Icon::render($item['icon'] ?? ($recordData['icon'] ?? null), $item['title'] ?? '', 'kl-icon') ?: \App\Support\Icon::svg('grid', 'kl-icon') !!}</span>
                        @endif
                    </div>
                    <div class="kl-facility__body">
                        <div>
                            <h3 class="kl-facility__title">{{ $item['title'] ?? '' }}</h3>
                            @if (!empty($item['description']))<p class="kl-facility__text">{{ $item['description'] }}</p>@endif
                        </div>
                        @if ($cardUrl)
                            <a href="{{ $cardUrl }}" class="kl-circle-link stretched-link" aria-label="{{ ($item['link_label'] ?? 'Explore') . ' ' . ($item['title'] ?? '') }}">
                                {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

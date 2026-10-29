@php
    $d = $section->data ?? [];
    $eyebrowLower = strtolower($d['eyebrow'] ?? '');
    $sectionId = str_contains($eyebrowLower, 'program')
        ? 'program-list'
        : (str_contains($eyebrowLower, 'sport')
            ? 'sports-list'
            : (str_contains($eyebrowLower, 'facilit')
                ? 'facility-list'
                : \Illuminate\Support\Str::slug($d['eyebrow'] ?? $d['title'] ?? 'cards')));

    // Cards without their own image reuse the image of the program/facility/sport record with the same title.
    $records = collect([$programs ?? collect(), $facilities ?? collect(), $sports ?? collect()])
        ->flatten(1)
        ->keyBy(fn ($record) => strtolower(trim($record->title)));
    $badgeColors = ['var(--kelika-green)', 'var(--kelika-gold)', 'var(--kelika-terracotta)', '#1f3d4c'];
@endphp
@php
    $sectionLayout = \App\Support\SectionDefaults::layout($section);
@endphp
@if ($sectionLayout !== 'grid')
@include('public.sections.layouts.card_grid-'.$sectionLayout)
@else
<section class="kl-section kl-cards" id="{{ $sectionId }}">
    <div class="container">
        <div class="kl-section-head">
            <div>
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section)])
                @if (!empty($d['subtitle']))
                    <p class="kl-muted mt-2 mb-0">{{ $d['subtitle'] }}</p>
                @endif
            </div>

            @if (!empty($d['button_label']) && !empty($d['button_url']))
                <a class="kl-link-arrow" href="{{ $d['button_url'] }}">
                    {{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                </a>
            @endif
        </div>

        <div class="row g-3 g-lg-4">
            @forelse (($d['items'] ?? []) as $item)
                @php
                    $record = $records->get(strtolower(trim((string) ($item['title'] ?? ''))));
                    $recordData = $record?->data ?? [];
                    $usesOwnImage = !empty($item['image']);
                    $cardImage = \App\Support\Media::url($usesOwnImage ? $item['image'] : ($recordData['image'] ?? null));
                    $cardMobileImage = \App\Support\Media::url($usesOwnImage ? ($item['mobile_image'] ?? null) : ($recordData['mobile_image'] ?? null));
                    $cardAlt = $usesOwnImage ? ($item['image_alt'] ?? '') : ($recordData['image_alt'] ?? '');
                    $cardPosition = ($usesOwnImage ? ($item['image_position'] ?? null) : ($recordData['image_position'] ?? null)) ?: 'center';
                    $cardIcon = \App\Support\Icon::render($item['icon'] ?? ($recordData['icon'] ?? null), $item['title'] ?? '', 'kl-icon');
                    $badgeColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : $badgeColors[$loop->index % count($badgeColors)];
                    // Without an image the public card leads with its icon badge instead of an empty image box.
                    $showMedia = $cardImage || ($preview ?? false);
                @endphp
                <div class="col-sm-6 col-lg-3">
                    <article class="kl-card {{ $showMedia ? '' : 'kl-card--no-media' }}">
                        <div class="kl-card__media">
                            @if ($cardImage)
                                <x-picture :image="$usesOwnImage ? $item['image'] : ($recordData['image'] ?? null)"
                                           :mobile="$usesOwnImage ? ($item['mobile_image'] ?? null) : ($recordData['mobile_image'] ?? null)"
                                           :alt="$cardAlt" :img-style="'object-position:'.$cardPosition" sizes="(min-width: 1400px) 420px, (min-width: 992px) 32vw, (min-width: 576px) 60vw, 100vw" mobile-sizes="(min-width: 1400px) 420px, (min-width: 992px) 32vw, (min-width: 576px) 60vw, 100vw" :dimensions="true" />
                            @elseif ($preview ?? false)
                                <div class="image-placeholder">Select card image in CMS</div>
                            @endif
                            @if ($cardIcon)
                                <span class="kl-card__badge" style="background:{{ $badgeColor }}">{!! $cardIcon !!}</span>
                            @endif
                        </div>

                        <div class="kl-card__body">
                            <div>
                                <h3 class="kl-card__title">{{ $item['title'] ?? '' }}</h3>
                                @if (!empty($item['description']))
                                    <p class="kl-card__text">{{ $item['description'] }}</p>
                                @endif
                            </div>
                            @if (!empty($item['url']))
                                <a href="{{ $item['url'] }}" class="kl-circle-link stretched-link" aria-label="{{ ($item['link_label'] ?? 'Explore') . ' ' . ($item['title'] ?? '') }}">
                                    {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                                </a>
                            @endif
                        </div>
                    </article>
                </div>
            @empty
                @if ($preview ?? false)
                    <div class="col-12">
                        <div class="empty-state">No cards have been added to this section yet. Add them in the CMS.</div>
                    </div>
                @endif
            @endforelse
        </div>
    </div>
</section>
@endif

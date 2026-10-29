{{-- Card grid, "teaser" layout (Sports design, "More sports"): a numbered intro — eyebrow, title, subtitle as a tagline,
     body text — beside a row of image cards. The number continues the sport bands above it (one more than the number
     of published sports); without sports it is left out. --}}
@php
    $teaserNumber = ($sports ?? collect())->isNotEmpty() ? str_pad((string) (($sports ?? collect())->count() + 1), 2, '0', STR_PAD_LEFT) : null;
@endphp
<section class="kl-section kl-teaser" id="{{ $sectionId }}">
    <div class="container kl-teaser__inner">
        <div class="kl-teaser__intro">
            @if ($teaserNumber)<span class="kl-sport__number" aria-hidden="true">{{ $teaserNumber }}</span>@endif
            <div>
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-sport__title'])
                @if (!empty($d['subtitle']))<p class="kl-sport__tagline">{{ $d['subtitle'] }}</p>@endif
                @if (!empty($d['body']))<p class="kl-sport__text">{{ $d['body'] }}</p>@endif
            </div>
        </div>
        <ul class="kl-teaser__cards">
            @foreach (($d['items'] ?? []) as $item)
                @php
                    $record = $records->get(strtolower(trim((string) ($item['title'] ?? ''))));
                    $cardImage = !empty($item['image']) ? $item['image'] : ($record?->data['image'] ?? null);
                @endphp
                <li class="kl-teaser__card">
                    <div class="kl-teaser__media">
                        @if (\App\Support\Media::url($cardImage))
                            <x-picture :image="$cardImage" :alt="!empty($item['image']) ? ($item['image_alt'] ?? '') : ($record?->data['image_alt'] ?? '')"
                                       :img-style="'object-position:'.(($item['image_position'] ?? null) ?: 'center')" sizes="(min-width: 992px) 15vw, (min-width: 576px) 45vw, 100vw" />
                        @else
                            <span class="kl-facility__icon">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'] ?? '', 'kl-icon') ?: \App\Support\Icon::svg('star', 'kl-icon') !!}</span>
                        @endif
                    </div>
                    <div class="kl-teaser__body">
                        <h3 class="kl-teaser__title">{{ $item['title'] ?? '' }}</h3>
                        @if (!empty($item['description']))<p class="kl-teaser__text">{{ $item['description'] }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>

{{-- Five elements, "cards" layout (Academy design, "Our approach"): heading beside the intro text, then a card per element
     with its image fading into the card, the element's icon, name, keyword and description. The element colour is the
     item's own colour when set, otherwise the brand setting for that element. --}}
@php($elementColors = [$siteSettings['earth_color'] ?? '#176b4e', $siteSettings['water_color'] ?? '#1687c9', $siteSettings['fire_color'] ?? '#d94737', $siteSettings['air_color'] ?? '#38a9ce', $siteSettings['space_color'] ?? '#8c45df'])
<section class="kl-section kl-elements" id="{{ \Illuminate\Support\Str::slug(($d['eyebrow'] ?? '') ?: 'five-elements') }}">
    <div class="container">
        <div class="kl-split-head">
            <div>
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
            </div>
            @if (!empty($d['subtitle']))<p class="kl-split-head__text">{{ $d['subtitle'] }}</p>@endif
        </div>

        <ul class="kl-elements__list">
            @foreach (($d['items'] ?? []) as $item)
                @php($elementColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : ($elementColors[$loop->index] ?? 'var(--kelika-green)'))
                <li class="kl-element" style="--kl-item: {{ $elementColor }}">
                    <div class="kl-element__media">
                        @if (\App\Support\Media::url($item['image'] ?? null))
                            <x-picture :image="$item['image']" :alt="$item['image_alt'] ?? ''" sizes="(min-width: 1200px) 260px, (min-width: 768px) 33vw, 50vw" />
                        @endif
                    </div>
                    <div class="kl-element__body">
                        <div class="kl-element__head">
                            <span class="kl-element__icon">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'] ?? '', 'kl-icon kl-icon--lg') !!}</span>
                            <div>
                                <h3 class="kl-element__name">{{ $item['title'] ?? '' }}</h3>
                                @if (!empty($item['keyword']))<p class="kl-element__keyword">{{ $item['keyword'] }}</p>@endif
                            </div>
                        </div>
                        @if (!empty($item['description']))<p class="kl-element__text">{{ $item['description'] }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>

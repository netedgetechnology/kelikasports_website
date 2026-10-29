{{-- Five elements, "lines" layout (About design, "Guided by the Five Elements"): heading beside the intro text, then one
     column per element with a large line icon, name, keyword and description in the element colour (site settings first,
     then the item's own colour), divided by thin rules. --}}
@php
    $elementColors = [$siteSettings['earth_color'] ?? null, $siteSettings['water_color'] ?? null, $siteSettings['fire_color'] ?? null, $siteSettings['air_color'] ?? null, $siteSettings['space_color'] ?? null];
    $elementIcons = ['leaf', 'waves', 'flame', 'wind', 'lotus'];
@endphp
<section class="kl-section kl-elements-lines" id="{{ \Illuminate\Support\Str::slug(($d['eyebrow'] ?? '') ?: 'our-philosophy') }}">
    <div class="container">
        <div class="kl-split-head">
            <div>
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
            </div>
            @if (!empty($d['subtitle']))<p class="kl-split-head__text">{{ $d['subtitle'] }}</p>@endif
        </div>
        <ul class="kl-elements-lines__list">
            @foreach (($d['items'] ?? []) as $item)
                @php
                    $itemColor = ($elementColors[$loop->index] ?? null) ?: (preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : 'var(--kelika-green)');
                    $icon = \App\Support\Icon::render($item['icon'] ?? null, $item['title'] ?? '', 'kl-icon kl-icon--xl');
                    // Named icons are drawn as given; the starter glyphs (e.g. ≈) fall back to this design's element icons.
                    if (! \App\Support\Icon::resolve($item['icon'] ?? null)) $icon = \App\Support\Icon::svg($elementIcons[$loop->index] ?? 'star', 'kl-icon kl-icon--xl');
                @endphp
                <li class="kl-element-line" style="--kl-item: {{ $itemColor }}">
                    <span class="kl-element-line__icon">{!! $icon !!}</span>
                    <h3 class="kl-element-line__name">{{ $item['title'] ?? '' }}</h3>
                    @if (!empty($item['keyword']))<p class="kl-element-line__keyword">{{ $item['keyword'] }}</p>@endif
                    @if (!empty($item['description']))<p class="kl-element-line__text">{{ $item['description'] }}</p>@endif
                </li>
            @endforeach
        </ul>
    </div>
</section>

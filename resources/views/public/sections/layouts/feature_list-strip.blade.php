{{-- Feature list, "strip" layout (Events design, "Why participate?"): a card with the heading above one row of features,
     each with its icon beside the title and text. --}}
@php($stripColors = ['#d4652e', '#c98a33', '#c98a33', '#c98a33'])
<section class="kl-section kl-why" id="{{ \Illuminate\Support\Str::slug(($d['eyebrow'] ?? '') ?: (($d['title'] ?? '') ?: 'why-participate')) }}">
    <div class="container">
        <div class="kl-why__panel">
            @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
            @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--bold kl-why__title'])
            @if (!empty($d['body']))<p class="kl-why__text">{{ $d['body'] }}</p>@endif
            <ul class="kl-why__list">
                @foreach (($d['items'] ?? []) as $item)
                    @php($itemColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : $stripColors[$loop->index % count($stripColors)])
                    <li class="kl-why__item" style="--kl-item: {{ $itemColor }}">
                        <span class="kl-why__icon">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'] ?? '', 'kl-icon kl-icon--xl') ?: \App\Support\Icon::svg('check', 'kl-icon kl-icon--xl') !!}</span>
                        <div>
                            <h3 class="kl-why__item-title">{{ $item['title'] ?? '' }}</h3>
                            @if (!empty($item['description']))<p class="kl-why__item-text">{{ $item['description'] }}</p>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

{{-- Feature list, "row" layout (Academy design, "Coaching benefits"): heading beside the intro text, then one row of
     features, each with its icon on a soft circle in the item colour. --}}
@php($rowColors = ['#c43d45', '#c98a33', '#1f8a52', '#2b6f8e', '#d4652e', '#6c45b8'])
<section class="kl-section kl-benefits" id="{{ \Illuminate\Support\Str::slug(($d['eyebrow'] ?? '') ?: 'benefits') }}">
    <div class="container">
        <div class="kl-split-head">
            <div>
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
            </div>
            @if (!empty($d['body']))<p class="kl-split-head__text">{{ $d['body'] }}</p>@endif
        </div>

        <ul class="kl-benefits__list">
            @foreach (($d['items'] ?? []) as $item)
                @php($benefitColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : $rowColors[$loop->index % count($rowColors)])
                <li class="kl-benefit" style="--kl-item: {{ $benefitColor }}">
                    <span class="kl-benefit__icon">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'] ?? '', 'kl-icon kl-icon--lg') ?: \App\Support\Icon::svg('check', 'kl-icon kl-icon--lg') !!}</span>
                    <h3 class="kl-benefit__title">{{ $item['title'] ?? '' }}</h3>
                    @if (!empty($item['description']))<p class="kl-benefit__text">{{ $item['description'] }}</p>@endif
                </li>
            @endforeach
        </ul>
    </div>
</section>

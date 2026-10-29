{{-- Feature list, "numbered" layout (Programs design, "What Your Child Will Learn"): heading beside the intro text, then
     one card per feature with its icon on a soft circle of the item colour and a 01, 02… number. --}}
@php($numberedColors = ['#1f8a52', '#c43d45', '#2b6f8e', '#d4652e'])
<section class="kl-section kl-learn" id="{{ \Illuminate\Support\Str::slug(($d['eyebrow'] ?? '') ?: 'program-details') }}">
    <div class="container">
        <div class="kl-split-head">
            <div>
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
            </div>
            @if (!empty($d['body']))<p class="kl-split-head__text">{{ $d['body'] }}</p>@endif
        </div>

        <ol class="kl-learn__list">
            @foreach (($d['items'] ?? []) as $item)
                @php($itemColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : $numberedColors[$loop->index % count($numberedColors)])
                <li class="kl-learn__card" style="--kl-item: {{ $itemColor }}">
                    <div class="kl-learn__head">
                        <span class="kl-learn__icon">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'] ?? '', 'kl-icon kl-icon--lg') ?: \App\Support\Icon::svg('check', 'kl-icon kl-icon--lg') !!}</span>
                        <span class="kl-learn__number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <h3 class="kl-learn__title">{{ $item['title'] ?? '' }}</h3>
                    @if (!empty($item['description']))<p class="kl-learn__text">{{ $item['description'] }}</p>@endif
                </li>
            @endforeach
        </ol>
    </div>
</section>

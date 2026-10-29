{{-- Feature list, "guide" layout (Sports design, "Which sport is right for you?"): eyebrow, heading, text and button on
     the left; centred icon features in the item colours on the right. Words wrapped in *asterisks* in the heading are
     highlighted in terracotta. --}}
@php($guideColors = ['var(--element-earth)', 'var(--kelika-terracotta)', 'var(--kelika-gold)', 'var(--element-water)'])
<section class="kl-section kl-guide" id="{{ \Illuminate\Support\Str::slug(($d['eyebrow'] ?? '') ?: 'which-sport') }}">
    <div class="container kl-guide__inner">
        <div class="kl-guide__intro">
            @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
            @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--bold kl-guide__title'])
            @if (!empty($d['body']))<p class="kl-guide__text">{{ $d['body'] }}</p>@endif
            @if (!empty($d['button_label']))
                <a class="btn btn-kelika" href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : route('page.show', ['slug' => 'contact']) }}">
                    {{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                </a>
            @endif
        </div>
        <ul class="kl-guide__list">
            @foreach (($d['items'] ?? []) as $item)
                @php($guideColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : $guideColors[$loop->index % count($guideColors)])
                <li class="kl-guide__item" style="--kl-item: {{ $guideColor }}">
                    <span class="kl-guide__icon">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'] ?? '', 'kl-icon kl-icon--xl') ?: \App\Support\Icon::svg('check', 'kl-icon kl-icon--xl') !!}</span>
                    <h3 class="kl-guide__item-title">{{ $item['title'] ?? '' }}</h3>
                    @if (!empty($item['description']))<p class="kl-guide__item-text">{{ $item['description'] }}</p>@endif
                </li>
            @endforeach
        </ul>
    </div>
</section>

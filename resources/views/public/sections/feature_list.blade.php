@php($d = \App\Support\SectionDefaults::apply($section))
@php($sectionLayout = \App\Support\SectionDefaults::layout($section))
@if ($sectionLayout !== 'columns')
@include('public.sections.layouts.feature_list-'.$sectionLayout)
@else
@php($iconColors = ['var(--kelika-terracotta)', 'var(--kelika-terracotta)', 'var(--kelika-green)'])
<section class="kl-section kl-features">
    <div class="container">
        <div class="row g-4 g-xl-5 align-items-start">
            <div class="col-lg-4">
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--bold'])
                @if (!empty($d['body']))<p class="kl-muted mt-3">{{ $d['body'] }}</p>@endif
                @if (!empty($d['button_label']))
                    <a class="btn btn-kelika mt-3" href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : route('page.show', ['slug' => 'about']) }}">
                        {{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                    </a>
                @endif
            </div>
            <div class="col-lg-8">
                <ul class="kl-feature-grid">
                    @foreach (($d['items'] ?? []) as $item)
                        @php($featureColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : $iconColors[$loop->index % count($iconColors)])
                        <li class="kl-feature">
                            <span class="kl-feature__icon" style="color:{{ $featureColor }}">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'] ?? '', 'kl-icon kl-icon--xl') ?: \App\Support\Icon::svg('check', 'kl-icon kl-icon--xl') !!}</span>
                            <h3 class="kl-feature__title">{{ $item['title'] ?? '' }}</h3>
                            @if (!empty($item['description']))<p class="kl-feature__text">{{ $item['description'] }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>
@endif

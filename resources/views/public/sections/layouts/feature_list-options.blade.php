{{-- Feature list, "options" layout (Programs design, "Flexible Options for Every Family"): heading on the left, intro
     text and one card per option on the right. Each option's description is a checklist: one point per line (or per |). --}}
@php($optionColors = ['#c98a33', '#d4652e', '#c43d45'])
<section class="kl-section kl-options" id="{{ \Illuminate\Support\Str::slug(($d['eyebrow'] ?? '') ?: 'program-schedule') }}">
    <div class="container">
        <div class="kl-options__inner">
            <div class="kl-options__intro">
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
            </div>
            <div class="kl-options__main">
                @if (!empty($d['body']))<p class="kl-options__text">{{ $d['body'] }}</p>@endif
                <ul class="kl-options__list">
                    @foreach (($d['items'] ?? []) as $item)
                        @php($itemColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : $optionColors[$loop->index % count($optionColors)])
                        @php($points = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|\|/', (string) ($item['description'] ?? ''))), fn ($point) => $point !== '')))
                        <li class="kl-option" style="--kl-item: {{ $itemColor }}">
                            <span class="kl-option__icon">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'] ?? '', 'kl-icon kl-icon--xl') ?: \App\Support\Icon::svg('check', 'kl-icon kl-icon--xl') !!}</span>
                            <h3 class="kl-option__title">{{ $item['title'] ?? '' }}</h3>
                            @if ($points)
                                <ul class="kl-option__points">
                                    @foreach ($points as $point)
                                        <li>{!! \App\Support\Icon::svg('check', 'kl-icon') !!}<span>{{ $point }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>

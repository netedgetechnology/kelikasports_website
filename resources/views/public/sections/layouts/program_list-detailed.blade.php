{{-- Program list, "detailed" layout (Academy design, "Training programs"): heading beside the intro text and a button,
     then a card per published program with its image, age range, highlights (the program's Features) and a button. --}}
@php
    $programsUrl = route('page.show', ['slug' => 'programs']).'#program-list';
@endphp
<section class="kl-section kl-programs" id="program-list">
    <div class="container">
        <div class="kl-split-head">
            <div>
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
            </div>
            @if (!empty($d['subtitle']) || !empty($d['button_label']))
                <div class="kl-split-head__aside">
                    @if (!empty($d['subtitle']))<p class="kl-split-head__text">{{ $d['subtitle'] }}</p>@endif
                    @if (!empty($d['button_label']))
                        <a class="btn btn-kelika-outline" href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : route('page.show', ['slug' => 'programs']) }}">
                            {{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <div class="kl-programs__grid">
            @forelse ($programs as $item)
                @php
                    $highlights = array_values(array_filter($item->data['features'] ?? [], fn ($feature) => is_array($feature) && trim((string) ($feature['title'] ?? '')) !== ''));
                    $programUrl = \App\Support\SafeUrl::isSafe($item->data['url'] ?? null) && !empty($item->data['url']) ? $item->data['url'] : $programsUrl;
                @endphp
                <article class="kl-program">
                    <div class="kl-program__media">
                        @if (!empty($item->data['image']))
                            <x-picture :image="$item->data['image']" :mobile="$item->data['mobile_image'] ?? null" :alt="$item->data['image_alt'] ?? $item->title"
                                       :img-style="'object-position:'.($item->data['image_position'] ?? 'center')" sizes="(min-width: 1200px) 320px, (min-width: 768px) 46vw, 100vw" mobile-sizes="100vw" />
                        @elseif ($preview ?? false)
                            <div class="image-placeholder">Select program image in CMS</div>
                        @endif
                    </div>
                    <div class="kl-program__body">
                        <h3 class="kl-program__title">{{ $item->title }}</h3>
                        @if (!empty($item->data['age_range']))<p class="kl-program__meta">{{ $item->data['age_range'] }}</p>@endif
                        @if ($highlights)
                            <ul class="kl-program__list">
                                @foreach ($highlights as $feature)
                                    <li>{!! \App\Support\Icon::svg('check', 'kl-icon') !!}<span>{{ $feature['title'] }}</span></li>
                                @endforeach
                            </ul>
                        @elseif (!empty($item->data['description']))
                            <p class="kl-program__text">{{ $item->data['description'] }}</p>
                        @endif
                        <a class="btn btn-kelika-outline kl-program__button" href="{{ $programUrl }}" aria-label="Learn more about {{ $item->title }}">
                            Learn more {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                        </a>
                    </div>
                </article>
            @empty
                @if ($preview ?? false)
                    <div class="empty-state">Programs will appear here after they are added and published in the CMS.</div>
                @endif
            @endforelse
        </div>
    </div>
</section>

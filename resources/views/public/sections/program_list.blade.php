@php
    $d = $section->data ?? [];
@endphp
@php
    $sectionLayout = \App\Support\SectionDefaults::layout($section);
@endphp
@if ($sectionLayout !== 'simple')
@include('public.sections.layouts.program_list-'.$sectionLayout)
@else
<section class="section-pad" id="program-list">
    <div class="container">
        <p class="eyebrow">{{ $d['eyebrow'] ?? 'Our Programs' }}</p>

        <h2 class="section-title mb-4">
            @foreach (explode('|', (string) ($d['title'] ?? 'Programs for Every Stage')) as $line)
                <span class="{{ $loop->index === 1 ? 'accent-terracotta' : ($loop->index === 2 ? 'accent-gold' : '') }}">{{ $line }}</span>
                @if (! $loop->last)
                    <br>
                @endif
            @endforeach
        </h2>

        <div class="row g-4">
            @forelse ($programs as $item)
                <div class="col-md-6 col-lg-3">
                    <article class="soft-card">
                        @if (! empty($item->data['image']))
                            <x-picture :image="$item->data['image']" :mobile="$item->data['mobile_image'] ?? null" img-class="w-100"
                                       :img-style="'aspect-ratio: 1.5; object-fit: cover; object-position: '.($item->data['image_position'] ?? 'center')"
                                       :alt="$item->data['image_alt'] ?? $item->title" sizes="(min-width: 1400px) 420px, (min-width: 992px) 32vw, (min-width: 576px) 60vw, 100vw" mobile-sizes="(min-width: 1400px) 420px, (min-width: 992px) 32vw, (min-width: 576px) 60vw, 100vw" />
                        @elseif ($preview ?? false)
                            <div class="image-placeholder">Select program image in CMS</div>
                        @else
                            <div class="image-placeholder" aria-hidden="true"></div>
                        @endif

                        <div class="card-body">
                            <h3 class="h5 fw-bold">{{ $item->title }}</h3>

                            @if (! empty($item->data['age_range']))
                                <p class="small fw-bold mb-2">{{ $item->data['age_range'] }}</p>
                            @endif

                            @if(!empty($item->data['description']))<p class="text-secondary small">{{ $item->data['description'] }}</p>@endif

                            @if (! empty($item->data['url']))
                                <a class="fw-bold text-decoration-none" href="{{ $item->data['url'] }}">Explore program →</a>
                            @endif
                        </div>
                    </article>
                </div>
            @empty
                @if ($preview ?? false)
                    <div class="col-12">
                        <div class="empty-state">Programs will appear here after they are added and published in the CMS.</div>
                    </div>
                @endif
            @endforelse
        </div>
    </div>
</section>
@endif

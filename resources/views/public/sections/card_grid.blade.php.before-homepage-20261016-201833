@php
    $d = $section->data ?? [];
    $eyebrowLower = strtolower($d['eyebrow'] ?? '');
    $sectionId = str_contains($eyebrowLower, 'program')
        ? 'program-list'
        : (str_contains($eyebrowLower, 'sport')
            ? 'sports-list'
            : (str_contains($eyebrowLower, 'facilit')
                ? 'facility-list'
                : \Illuminate\Support\Str::slug($d['eyebrow'] ?? $d['title'] ?? 'cards')));
@endphp

<section class="section-pad" id="{{ $sectionId }}">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
            <div>
                <p class="eyebrow">{{ $d['eyebrow'] ?? '' }}</p>

                <h2 class="section-title">
                    @foreach (explode('|', (string) ($d['title'] ?? '')) as $line)
                        <span class="{{ $loop->index === 1 ? 'accent-terracotta' : ($loop->index === 2 ? 'accent-gold' : '') }}">{{ $line }}</span>
                        @if (!$loop->last)
                            <br>
                        @endif
                    @endforeach
                </h2>

                @if (!empty($d['subtitle']))
                    <p class="text-secondary mt-2 mb-0">{{ $d['subtitle'] }}</p>
                @endif
            </div>

            @if (!empty($d['button_label']) && !empty($d['button_url']))
                <a class="btn btn-kelika-outline" href="{{ $d['button_url'] }}">
                    {{ $d['button_label'] }} →
                </a>
            @endif
        </div>

        <div class="row g-4">
            @forelse (($d['items'] ?? []) as $item)
                <div class="col-sm-6 col-lg-3">
                    <article class="soft-card">
                        @if (!empty($item['image']))
                            <picture>
                                @if (!empty($item['mobile_image']))
                                    <source
                                        media="(max-width: 767px)"
                                        srcset="{{ str_starts_with($item['mobile_image'], 'http') || str_starts_with($item['mobile_image'], '/') ? $item['mobile_image'] : \Illuminate\Support\Facades\Storage::disk('public')->url($item['mobile_image']) }}">
                                @endif

                                <img
                                    src="{{ str_starts_with($item['image'], 'http') || str_starts_with($item['image'], '/') ? $item['image'] : \Illuminate\Support\Facades\Storage::disk('public')->url($item['image']) }}"
                                    class="w-100"
                                    style="aspect-ratio:1.7;object-fit:cover;object-position:{{ $item['image_position'] ?? 'center' }}"
                                    alt="{{ $item['image_alt'] ?? '' }}"
                                    loading="lazy">
                            </picture>
                        @else
                            @if ($preview ?? false)
                                <div class="image-placeholder">Select card image in CMS</div>
                            @else
                                <div class="image-placeholder" aria-hidden="true"></div>
                            @endif
                        @endif

                        <div class="card-body">
                            <h3 class="h5 fw-bold">{{ $item['title'] ?? '' }}</h3>
                            <p class="text-secondary small mb-3">{{ $item['description'] ?? '' }}</p>

                            @if (!empty($item['url']))
                                <a href="{{ $item['url'] }}" class="fw-bold text-decoration-none">
                                    {{ $item['link_label'] ?? 'Explore' }} →
                                </a>
                            @endif
                        </div>
                    </article>
                </div>
            @empty
                @if ($preview ?? false)
                    <div class="col-12">
                        <div class="empty-state">
                            No cards have been added to this section yet. Add them in the CMS.
                        </div>
                    </div>
                @endif
            @endforelse
        </div>
    </div>
</section>

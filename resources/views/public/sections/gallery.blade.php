@php
    $d = $section->data ?? [];

    $galleryItems = ! empty($d['items'])
        ? $d['items']
        : ($galleries ?? collect())->map(fn ($item) => [
            'image' => $item->data['image'] ?? '',
            'mobile_image' => $item->data['mobile_image'] ?? '',
            'image_alt' => $item->data['image_alt'] ?? $item->title,
            'caption' => $item->data['caption'] ?? $item->title,
        ])->all();
@endphp

<section class="section-pad">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end gap-3 mb-4">
            <div>
                <p class="eyebrow">{{ $d['eyebrow'] ?? 'Gallery' }}</p>

                <h2 class="section-title">
                    @foreach (explode('|', (string) ($d['title'] ?? 'Moments at KELIKA')) as $line)
                        <span class="{{ $loop->index === 1 ? 'accent-terracotta' : ($loop->index === 2 ? 'accent-gold' : '') }}">{{ $line }}</span>
                        @if (! $loop->last)
                            <br>
                        @endif
                    @endforeach
                </h2>
            </div>
        </div>

        <div class="row g-3">
            @foreach ($galleryItems as $item)
                <div class="col-6 col-lg-3">
                    @if (! empty($item['image']))
                        <picture>
                            @if (! empty($item['mobile_image']))
                                <source
                                    media="(max-width: 767px)"
                                    srcset="{{ str_starts_with($item['mobile_image'], 'http') || str_starts_with($item['mobile_image'], '/') ? $item['mobile_image'] : \Illuminate\Support\Facades\Storage::disk('public')->url($item['mobile_image']) }}">
                            @endif

                            <img
                                class="w-100 rounded-3"
                                style="aspect-ratio: 1.3; object-fit: cover"
                                src="{{ str_starts_with($item['image'], 'http') || str_starts_with($item['image'], '/') ? $item['image'] : \Illuminate\Support\Facades\Storage::disk('public')->url($item['image']) }}"
                                alt="{{ $item['image_alt'] ?? '' }}"
                                loading="lazy">
                        </picture>
                    @elseif ($preview ?? false)
                        <div class="image-placeholder rounded-3">Add gallery image</div>
                    @else
                        <div class="image-placeholder rounded-3" aria-hidden="true"></div>
                    @endif

                    @if (! empty($item['caption']))
                        <p class="small text-secondary mt-2">{{ $item['caption'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>

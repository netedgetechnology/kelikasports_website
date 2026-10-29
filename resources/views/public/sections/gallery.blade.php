@php
    $d = $section->data ?? [];
@endphp
@if (\App\Support\SectionDefaults::layout($section) === 'strip')
@include('public.sections.layouts.gallery-strip')
@else
@php
    $galleryItems = ! empty($d['items'])
        ? $d['items']
        : ($galleries ?? collect())->map(fn ($item) => [
            'image' => $item->data['image'] ?? '',
            'mobile_image' => $item->data['mobile_image'] ?? '',
            'image_alt' => $item->data['image_alt'] ?? $item->title,
            'caption' => $item->data['caption'] ?? $item->title,
        ])->all();
@endphp

{{-- No gallery images yet: visitors see nothing; CMS preview shows where to add them. --}}
@if ($galleryItems || ($preview ?? false))
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

        @if (! $galleryItems)
            @include('public.partials.preview-note', ['message' => 'No gallery images yet — this section is hidden from visitors until images are added.'])
        @endif
        <div class="row g-3">
            @foreach ($galleryItems as $item)
                <div class="col-6 col-lg-3">
                    @if (! empty($item['image']))
                        <x-picture :image="$item['image']" :mobile="$item['mobile_image'] ?: null" img-class="w-100 rounded-3" img-style="aspect-ratio: 1.3; object-fit: cover"
                                   :alt="$item['image_alt'] ?? ''" sizes="(min-width: 992px) 32vw, 60vw" mobile-sizes="60vw" />
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
@endif
@endif

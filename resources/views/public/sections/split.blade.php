@php
    $d = $section->data ?? [];
@endphp

<section class="section-pad"
         id="{{ \Illuminate\Support\Str::slug($d['eyebrow'] ?? $d['title'] ?? 'split') }}">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <p class="eyebrow">{{ $d['eyebrow'] ?? '' }}</p>

                <h2 class="section-title">
                    @foreach (explode('|', (string) ($d['title'] ?? '')) as $line)
                        <span class="{{ $loop->index === 1 ? 'accent-terracotta' : ($loop->index === 2 ? 'accent-gold' : '') }}">{{ $line }}</span>
                        @if (! $loop->last)
                            <br>
                        @endif
                    @endforeach
                </h2>

                @if (! empty($d['subtitle']))
                    <p class="lead mt-3">{{ $d['subtitle'] }}</p>
                @endif

                @if (! empty($d['body']))
                    <p class="text-secondary mt-3">{!! nl2br(e($d['body'])) !!}</p>
                @endif

                @if (! empty($d['button_label']))
                    <a class="btn btn-kelika mt-3"
                       href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && ! empty($d['button_url']) ? $d['button_url'] : route('page.show', ['slug' => 'contact']) }}">
                        {{ $d['button_label'] }} →
                    </a>
                @endif
            </div>

            <div class="col-lg-7">
                @if (! empty($d['image']))
                    <picture>
                        @if (! empty($d['mobile_image']))
                            <source
                                media="(max-width: 767px)"
                                srcset="{{ str_starts_with($d['mobile_image'], 'http') || str_starts_with($d['mobile_image'], '/') ? $d['mobile_image'] : \Illuminate\Support\Facades\Storage::disk('public')->url($d['mobile_image']) }}">
                        @endif

                        <img
                            class="w-100 rounded-3"
                            loading="lazy"
                            src="{{ str_starts_with($d['image'], 'http') || str_starts_with($d['image'], '/') ? $d['image'] : \Illuminate\Support\Facades\Storage::disk('public')->url($d['image']) }}"
                            alt="{{ $d['image_alt'] ?? '' }}"
                            style="object-position: {{ $d['image_position'] ?? 'center' }}">
                    </picture>
                @else
                    @if ($preview ?? false)
                        <div class="image-placeholder rounded-3"
                             aria-label="Image to be selected in CMS">
                            Image to be selected in CMS
                        </div>
                    @else
                        <div class="image-placeholder rounded-3" aria-hidden="true"></div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</section>

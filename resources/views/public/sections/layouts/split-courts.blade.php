{{-- Split, "courts" layout (Facilities design, "Main attraction"): copy and button beside a wide photo that runs to the
     viewport's right edge, then the section's items as a row of image cards (title, text, optional link). Without an
     image the copy spans the width. --}}
@php
    $courtCards = array_values(array_filter($d['items'] ?? [], fn ($item) => is_array($item) && trim((string) ($item['title'] ?? '')) !== ''));
    $bookingFallback = ($siteSettings['booking_url'] ?? '') ?: route('page.show', ['slug' => 'contact', 'topic' => 'Court Booking']).'#contact-form';
@endphp
<section @class(['kl-section', 'kl-courts', 'kl-courts--no-media' => ! $showMedia]) id="{{ \Illuminate\Support\Str::slug(($d['eyebrow'] ?? '') ?: ($d['title'] ?? 'main-attraction')) }}">
    <div class="kl-courts__top">
        <div class="kl-courts__copy">
            @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
            @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
            @if (!empty($d['body']))<p class="kl-courts__text">{!! nl2br(e($d['body'])) !!}</p>@endif
            @if (!empty($d['button_label']))
                <a class="btn btn-kelika" href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : $bookingFallback }}">
                    {{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                </a>
            @endif
        </div>
        @if ($showMedia)
            <div class="kl-courts__media">
                @if ($splitImage)
                    <x-picture :image="$d['image'] ?? null" :mobile="$d['mobile_image'] ?? null" :alt="$d['image_alt'] ?? ''"
                               :img-style="'object-position:'.($d['image_position'] ?? 'center')" sizes="(min-width: 992px) 58vw, 100vw" :dimensions="true" />
                @else
                    <div class="image-placeholder">Select an image in the CMS</div>
                @endif
            </div>
        @endif
    </div>

    @if ($courtCards)
        <div class="container">
            <ul class="kl-courts__cards">
                @foreach ($courtCards as $item)
                    @php($cardUrl = \App\Support\SafeUrl::isSafe($item['url'] ?? null) && !empty($item['url']) ? $item['url'] : null)
                    <li class="kl-court-card">
                        <div class="kl-court-card__media">
                            @if (!empty($item['image']))
                                <x-picture :image="$item['image']" :alt="$item['image_alt'] ?? ''" :img-style="'object-position:'.($item['image_position'] ?? 'center')"
                                           sizes="(min-width: 1200px) 320px, (min-width: 576px) 46vw, 100vw" mobile-sizes="100vw" />
                            @elseif ($preview ?? false)
                                <div class="image-placeholder">Select an image in the CMS</div>
                            @else
                                <span class="kl-court-card__icon">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'], 'kl-icon kl-icon--xl') ?: \App\Support\Icon::svg('court', 'kl-icon kl-icon--xl') !!}</span>
                            @endif
                        </div>
                        <div class="kl-court-card__body">
                            <h3 class="kl-court-card__title">@if ($cardUrl)<a class="stretched-link" href="{{ $cardUrl }}">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif</h3>
                            @if (!empty($item['description']))<p class="kl-court-card__text">{{ $item['description'] }}</p>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>

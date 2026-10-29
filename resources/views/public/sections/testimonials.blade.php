@php
    $d = $section->data ?? [];
    $quotes = $testimonials ?? collect();
    // Always rendered. Without approved testimonials the three card positions show a neutral message — no names, quotes or portraits.
    $quotesEmpty = $quotes->isEmpty();
@endphp
<section class="kl-section kl-testimonials" data-carousel>
    <div class="container">
        <div class="row g-4 g-xl-5">
            <div class="col-lg-3">
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? 'Real People.|Real Progress.', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--bold'])
                @if (!empty($d['subtitle']))<p class="kl-muted mt-3">{{ $d['subtitle'] }}</p>@endif
                @if ($quotes->count() > 1 || $quotesEmpty)
                    <div class="kl-carousel-controls">
                        <button type="button" class="kl-circle-link" data-carousel-prev aria-label="Previous testimonials">{!! \App\Support\Icon::svg('chevron-left', 'kl-icon kl-icon--sm') !!}</button>
                        <button type="button" class="kl-circle-link" data-carousel-next aria-label="Next testimonials">{!! \App\Support\Icon::svg('chevron-right', 'kl-icon kl-icon--sm') !!}</button>
                    </div>
                @endif
            </div>
            <div class="col-lg-9">
                @if (($preview ?? false) && $quotes->contains(fn ($quote) => (bool) ($quote->data['demo'] ?? false)))
                    @include('public.partials.preview-note', ['message' => 'Demo testimonials — reference wording, not verified endorsements. Shown only in CMS preview while marked as demo.'])
                @endif
                @if ($quotesEmpty && ($preview ?? false))
                    @include('public.partials.preview-note', ['message' => 'No approved testimonials are published — visitors see the neutral cards below. Add testimonials under Content → Testimonials.'])
                @endif
                <div class="kl-quote-track" data-carousel-track tabindex="0" aria-label="Testimonials">
                    @foreach ($quotes as $quote)
                        @php
                            $quoteName = ($quote->data['name'] ?? '') ?: $quote->title;
                            $quoteRole = ($quote->data['role'] ?? '') ?: ($quote->data['relationship'] ?? '');
                            $quoteImage = \App\Support\Media::url($quote->data['image'] ?? null);
                        @endphp
                        <figure class="kl-quote">
                            {!! \App\Support\Icon::svg('quote', 'kl-icon kl-quote__mark') !!}
                            <blockquote>{{ $quote->data['quote'] ?? '' }}</blockquote>
                            <figcaption>
                                @if ($quoteImage)
                                    <img src="{{ $quoteImage }}" alt="{{ $quote->data['image_alt'] ?? '' }}" width="44" height="44" loading="lazy">
                                @else
                                    <span class="kl-quote__initial" aria-hidden="true">{{ collect(preg_split('/\s+/u', trim($quoteName)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}</span>
                                @endif
                                <span><strong>{{ $quoteName }}</strong>@if ($quoteRole)<small>{{ $quoteRole }}</small>@endif</span>
                            </figcaption>
                        </figure>
                    @endforeach
                    @if ($quotesEmpty)
                        @for ($i = 0; $i < 3; $i++)
                            <div class="kl-quote kl-quote--empty">
                                {!! \App\Support\Icon::svg('quote', 'kl-icon kl-quote__mark') !!}
                                <p class="kl-quote__text">Stories from the KELIKA community will be shared here soon.</p>
                                <div class="kl-quote__caption">
                                    <span class="kl-quote__initial" aria-hidden="true">K</span>
                                    <span><strong>{{ $siteSettings['site_name'] ?? 'KELIKA' }}</strong><small>Community stories coming soon</small></span>
                                </div>
                            </div>
                        @endfor
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

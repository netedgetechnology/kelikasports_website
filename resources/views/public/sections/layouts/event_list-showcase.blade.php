{{-- Event list, "showcase" layout (Events design): a green featured-event panel with its photo on an angled edge, beside
     an "Upcoming events" panel listing the events with date badges and thumbnails. The featured event is the one marked
     as featured, otherwise the first upcoming event; the list shows the upcoming events (featured included), as in the
     reference. Visibility is decided by the controller as for every layout: demo records reach visitors only in
     development demo mode. Links appear only for a registration or details page that is not this page. Without events,
     both panels show neutral announcements — no dates, places or details. --}}
@php
    $showcaseEvents = ($events ?? collect())->take(4);
    $spotlight = $featuredEvent ?? $showcaseEvents->first();
    $sectionImage = \App\Support\Media::url($d['image'] ?? null) ? ($d['image'] ?? null) : null;
    $eventLinkFor = function ($event) use ($isCurrentPage) {
        $link = ($event->data['registration_url'] ?? '') ?: ($event->data['url'] ?? null);

        return $link && \App\Support\SafeUrl::isSafe($link) && ! $isCurrentPage($link) ? $link : null;
    };
    // A confirmed date range ("15 – 17 Nov 2030"), a single date, the record's short label ("18 Dec"), or nothing.
    $eventDateFor = function ($event) {
        if ($event->starts_at && $event->ends_at && ! $event->ends_at->isSameDay($event->starts_at)) {
            return $event->starts_at->isSameMonth($event->ends_at)
                ? $event->starts_at->format('j').' – '.$event->ends_at->format('j M Y')
                : $event->starts_at->format('j M').' – '.$event->ends_at->format('j M Y');
        }

        return $event->starts_at?->format('j M Y') ?? (trim((string) ($event->data['date_label'] ?? '')) ?: null);
    };
    $badgeFor = function ($event) {
        if ($event->starts_at) return [$event->starts_at->format('d'), strtoupper($event->starts_at->format('M'))];
        $label = preg_split('/\s+/', trim((string) ($event->data['date_label'] ?? '')), 2);

        return $label[0] !== '' ? [$label[0], mb_strtoupper($label[1] ?? '')] : ['TBA', ''];
    };
    $viewAllUrl = \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) && ! str_starts_with((string) $d['button_url'], '#') && ! $isCurrentPage($d['button_url']) ? $d['button_url'] : null;
@endphp
<section class="kl-section kl-event-showcase" id="upcoming-events">
    <div class="container">
        @if (!empty($d['title']))<h2 class="visually-hidden">{{ str_replace(['|', '*'], [' ', ''], $d['title']) }}</h2>@endif

        @if (($preview ?? false) && $showcaseEvents->contains(fn ($event) => (bool) ($event->data['demo'] ?? false)))
            @include('public.partials.preview-note', ['message' => 'Demo events — unverified draft records shown only in CMS preview. They never appear on the public site while marked as demo.'])
        @endif

        <div class="kl-event-showcase__grid">
            @php
                $spotlightImage = $spotlight && \App\Support\Media::url($spotlight->data['image'] ?? null) ? $spotlight->data['image'] : $sectionImage;
                $spotlightAlt = $spotlight && ! empty($spotlight->data['image']) ? ($spotlight->data['image_alt'] ?? $spotlight->title) : ($d['image_alt'] ?? '');
            @endphp
            <article @class(['kl-feature-event', 'kl-feature-event--empty' => ! $spotlight, 'kl-feature-event--no-media' => ! $spotlightImage])>
                @if ($spotlightImage)
                    <div class="kl-feature-event__media">
                        <x-picture :image="$spotlightImage" :alt="$spotlightAlt" :img-style="'object-position:'.(($spotlight?->data['image_position'] ?? null) ?: ($d['image_position'] ?? 'center'))"
                                   sizes="(min-width: 992px) 40vw, 100vw" :dimensions="true" />
                    </div>
                @endif
                <div class="kl-feature-event__copy">
                    <p class="kl-feature-event__kicker">Featured Event</p>
                    @if ($spotlight)
                        @php
                            $spotlightLink = $eventLinkFor($spotlight);
                        @endphp
                        <h3 class="kl-feature-event__title">{{ $spotlight->title }}</h3>
                        @if (!empty($spotlight->data['summary']))<p class="kl-feature-event__text">{{ $spotlight->data['summary'] }}</p>@endif
                        <ul class="kl-feature-event__facts">
                            <li>{!! \App\Support\Icon::svg('calendar', 'kl-icon') !!}<span>{{ $eventDateFor($spotlight) ?? 'Date to be announced' }}</span></li>
                            @if (!empty($spotlight->data['location']))<li>{!! \App\Support\Icon::svg('pin', 'kl-icon') !!}<span>{{ $spotlight->data['location'] }}</span></li>@endif
                            @if (!empty($spotlight->data['formats']))<li>{!! \App\Support\Icon::svg('users', 'kl-icon') !!}<span>{{ $spotlight->data['formats'] }}</span></li>@endif
                            @if (!empty($spotlight->data['categories']) || !empty($spotlight->data['eyebrow']))<li>{!! \App\Support\Icon::svg('chart', 'kl-icon') !!}<span>{{ ($spotlight->data['categories'] ?? '') ?: $spotlight->data['eyebrow'] }}</span></li>@endif
                        </ul>
                        @if ($spotlightLink)
                            <a class="btn btn-kelika-gold" href="{{ $spotlightLink }}" @if (str_starts_with($spotlightLink, 'http')) target="_blank" rel="noopener noreferrer" @endif>
                                {{ !empty($spotlight->data['registration_url']) ? 'Register / Details' : 'View Details' }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                            </a>
                        @endif
                    @else
                        <h3 class="kl-feature-event__title">Next event to be announced</h3>
                        <p class="kl-feature-event__text">Confirmed event details will be published here. Contact our team for current information.</p>
                        <a class="btn btn-kelika-gold" href="{{ route('page.show', ['slug' => 'contact']) }}">Contact Us {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a>
                    @endif
                </div>
            </article>

            <div class="kl-event-list">
                <div class="kl-event-list__head">
                    <p class="kl-event-list__kicker">{{ ($d['eyebrow'] ?? '') ?: 'Upcoming Events' }}</p>
                    @if ($viewAllUrl && !empty($d['button_label']))
                        <a class="kl-event-list__all" href="{{ $viewAllUrl }}">{{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a>
                    @endif
                </div>
                @if ($showcaseEvents->isNotEmpty())
                    <ul class="kl-event-list__items">
                        @foreach ($showcaseEvents as $event)
                            @php
                                [$badgeDay, $badgeMonth] = $badgeFor($event);
                                $rowLink = $eventLinkFor($event);
                            @endphp
                            <li class="kl-event-row">
                                @if ($event->starts_at)
                                    <time class="kl-event-row__date" datetime="{{ $event->starts_at->toDateString() }}"><strong>{{ $badgeDay }}</strong>{{ $badgeMonth }}</time>
                                @else
                                    <span class="kl-event-row__date"><strong>{{ $badgeDay }}</strong>{{ $badgeMonth }}</span>
                                @endif
                                <div class="kl-event-row__thumb">
                                    @if (\App\Support\Media::url($event->data['image'] ?? null))
                                        <x-picture :image="$event->data['image']" :alt="$event->data['image_alt'] ?? $event->title" :img-style="'object-position:'.($event->data['image_position'] ?? 'center')" sizes="120px" mobile-sizes="120px" />
                                    @else
                                        <span class="kl-event-row__thumb-icon" aria-hidden="true">{!! \App\Support\Icon::svg('calendar', 'kl-icon') !!}</span>
                                    @endif
                                </div>
                                <div class="kl-event-row__body">
                                    <h3 class="kl-event-row__title">@if ($rowLink)<a class="stretched-link" href="{{ $rowLink }}" @if (str_starts_with($rowLink, 'http')) target="_blank" rel="noopener noreferrer" @endif>{{ $event->title }}</a>@else{{ $event->title }}@endif</h3>
                                    @if (!empty($event->data['eyebrow']))<p class="kl-event-row__meta">{{ $event->data['eyebrow'] }}</p>@endif
                                    @if (!empty($event->data['location']))<p class="kl-event-row__place">{!! \App\Support\Icon::svg('pin', 'kl-icon kl-icon--sm') !!} {{ $event->data['location'] }}</p>@endif
                                </div>
                                @if ($rowLink)<span class="kl-event-row__arrow" aria-hidden="true">{!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="kl-event-list__empty">
                        <span class="kl-event-list__empty-icon" aria-hidden="true">{!! \App\Support\Icon::svg('calendar', 'kl-icon kl-icon--lg') !!}</span>
                        <p class="kl-event-list__empty-title">Upcoming events will be listed here</p>
                        <p class="kl-event-list__empty-text">Dates, venues and details are published once each event is confirmed.</p>
                    </div>
                    @if ($preview ?? false)
                        @include('public.partials.preview-note', ['message' => 'No upcoming events are published — visitors see these neutral panels. Add events under Content → Events.'])
                    @endif
                @endif
            </div>
        </div>
    </div>
</section>

{{-- Event list, "cards" layout (Events design, "Upcoming events"): heading beside optional intro text, then the upcoming
     events (featured first) as three-column cards with image, date badge, category, title and location. Visibility is
     decided by the controller as for every layout: demo records reach visitors only in development demo mode. A card
     links only to a registration or details page that is not this page. Without events, neutral cards announce that
     events will be published here — no dates, places or details. --}}
@php
    $gridEvents = ($events ?? collect())->take(6);
@endphp
<section class="kl-section kl-events-grid" id="upcoming-events">
    <div class="container">
        <div class="kl-split-head">
            <div>
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? 'Upcoming Events', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
            </div>
            @if (!empty($d['subtitle']) || !empty($d['button_label']))
                <div class="kl-split-head__aside">
                    @if (!empty($d['subtitle']))<p class="kl-split-head__text">{{ $d['subtitle'] }}</p>@endif
                    @if (!empty($d['button_label']))
                        <a class="btn btn-kelika-outline" href="{{ $eventsButtonUrl }}">{{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a>
                    @endif
                </div>
            @endif
        </div>

        @if (($preview ?? false) && $gridEvents->contains(fn ($event) => (bool) ($event->data['demo'] ?? false)))
            @include('public.partials.preview-note', ['message' => 'Demo events — unverified draft records shown only in CMS preview. They never appear on the public site while marked as demo.'])
        @endif

        <ul class="kl-events-grid__list">
            @forelse ($gridEvents as $event)
                @php
                    $eventImage = \App\Support\Media::url($event->data['image'] ?? null);
                    $eventLink = ($event->data['registration_url'] ?? '') ?: ($event->data['url'] ?? null);
                    $eventLink = $eventLink && \App\Support\SafeUrl::isSafe($eventLink) && ! $isCurrentPage($eventLink) ? $eventLink : null;
                    // Without a confirmed start date, a short label such as "18 Dec" drives the badge (no year implied).
                    $dateLabel = preg_split('/\s+/', trim((string) ($event->data['date_label'] ?? '')), 2);
                @endphp
                <li>
                    <article class="kl-event">
                        <div class="kl-event__media">
                            @if ($eventImage)
                                <x-picture :contents="true" :image="$event->data['image'] ?? null" :alt="$event->data['image_alt'] ?? $event->title"
                                           :img-style="'object-position:'.($event->data['image_position'] ?? 'center')" sizes="(min-width: 992px) 30vw, (min-width: 576px) 46vw, 100vw" :dimensions="true" />
                            @else
                                <div class="kl-event__empty-visual" aria-hidden="true">{!! \App\Support\Icon::svg('calendar', 'kl-icon') !!}</div>
                            @endif
                            @if ($event->starts_at)
                                <time class="kl-event__date" datetime="{{ $event->starts_at->toDateString() }}">
                                    <strong>{{ $event->starts_at->format('d') }}</strong>{{ strtoupper($event->starts_at->format('M')) }}
                                </time>
                            @elseif ($dateLabel[0] !== '')
                                <span class="kl-event__date"><strong>{{ $dateLabel[0] }}</strong>{{ mb_strtoupper($dateLabel[1] ?? '') }}</span>
                            @endif
                        </div>
                        <div class="kl-event__body">
                            @if (!empty($event->data['eyebrow']))<p class="kl-events-grid__category">{{ $event->data['eyebrow'] }}</p>@endif
                            <h3 class="kl-events-grid__title">{{ $event->title }}</h3>
                            @if (!empty($event->data['summary']))<p class="kl-events-grid__text">{{ $event->data['summary'] }}</p>@endif
                            @if (!$event->starts_at && $dateLabel[0] === '')<p class="kl-event__meta">Date to be announced</p>@endif
                            <div class="kl-event__foot">
                                @if (!empty($event->data['location']))
                                    <span class="kl-event__meta">{!! \App\Support\Icon::svg('pin', 'kl-icon kl-icon--sm') !!} {{ $event->data['location'] }}</span>
                                @endif
                                @if ($eventLink)
                                    <a class="kl-circle-link stretched-link" href="{{ $eventLink }}" @if (str_starts_with($eventLink, 'http')) target="_blank" rel="noopener noreferrer" @endif aria-label="Details for {{ $event->title }}">{!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a>
                                @endif
                            </div>
                        </div>
                    </article>
                </li>
            @empty
                @if ($preview ?? false)
                    <li class="kl-events-grid__note">@include('public.partials.preview-note', ['message' => 'No upcoming events are published — visitors see the neutral cards below. Add events under Content → Events.'])</li>
                @endif
                @for ($i = 0; $i < 3; $i++)
                    <li>
                        <article class="kl-event kl-event--empty">
                            <div class="kl-event__media">
                                <div class="kl-event__empty-visual" aria-hidden="true">{!! \App\Support\Icon::svg('calendar', 'kl-icon') !!}</div>
                                <span class="kl-event__date"><strong>TBA</strong>DATE</span>
                            </div>
                            <div class="kl-event__body">
                                <h3 class="kl-events-grid__title">Event announcement coming soon</h3>
                                <p class="kl-event__meta">Details will be published here.</p>
                            </div>
                        </article>
                    </li>
                @endfor
            @endforelse
        </ul>
    </div>
</section>

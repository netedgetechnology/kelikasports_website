@php
    $d = $section->data ?? [];
    $layout = \App\Support\SectionDefaults::layout($section);
    $compactEvents = $layout === 'compact' ? ($events ?? collect())->take(3) : collect();
    $eventsButtonUrl = \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : route('page.show', ['slug' => 'events']);
    // Confirmed start date first, then the record's short date label (e.g. "18 Dec", as on the homepage badges).
    $eventDate = fn ($event) => $event->starts_at?->format('d M Y') ?? ((trim((string) ($event->data['date_label'] ?? '')) ?: null) ?? 'Date to be announced');
    // A "details" link that points back to the page being viewed is not a details page, so it is not shown.
    $isCurrentPage = fn ($url) => trim((string) parse_url((string) $url, PHP_URL_PATH), '/') === trim(request()->path(), '/');
@endphp

@if (in_array($layout, ['cards', 'showcase'], true))
@include('public.sections.layouts.event_list-'.$layout)
@elseif ($layout === 'compact')
    <section class="kl-section kl-events" id="upcoming-events">
        <div class="container">
            <div class="row g-4 g-xl-5">
                <div class="col-lg-3">
                    @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                    @include('public.partials.heading', ['title' => $d['title'] ?? 'Upcoming Events', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--bold'])
                    @if (!empty($d['subtitle']))<p class="kl-muted mt-3">{{ $d['subtitle'] }}</p>@endif
                    @if (!empty($d['button_label']))
                        <a class="btn btn-kelika mt-3" href="{{ $eventsButtonUrl }}">{{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a>
                    @endif
                </div>
                <div class="col-lg-9">
                    @if (($preview ?? false) && $compactEvents->contains(fn ($event) => (bool) ($event->data['demo'] ?? false)))
                        @include('public.partials.preview-note', ['message' => 'Demo events — unverified draft records shown only in CMS preview. They never appear on the public site while marked as demo.'])
                    @endif
                    <div class="row g-3">
                        @forelse ($compactEvents as $event)
                            @php
                                $eventImage = \App\Support\Media::url($event->data['image'] ?? null);
                                $eventLink = ($event->data['registration_url'] ?? '') ?: ($event->data['url'] ?? null);
                                // Without a confirmed start date, a short label such as "18 Dec" can drive the badge (no year implied).
                                $dateLabel = preg_split('/\s+/', trim((string) ($event->data['date_label'] ?? '')), 2);
                            @endphp
                            <div class="col-md-4">
                                <article class="kl-event">
                                    <div class="kl-event__media">
                                        @if ($eventImage)
                                            <x-picture :contents="true" :image="$event->data['image'] ?? null" :alt="$event->data['image_alt'] ?? $event->title"
                                                       :img-style="'object-position:'.($event->data['image_position'] ?? 'center')" sizes="(min-width: 768px) 40vw, 100vw" :dimensions="true" />
                                        @else
                                            <div class="image-placeholder" aria-hidden="true">@if ($preview ?? false) Add event image @endif</div>
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
                                        <h3 class="kl-card__title">{{ $event->title }}</h3>
                                        @if (!empty($event->data['eyebrow']))<p class="kl-event__meta">{{ $event->data['eyebrow'] }}</p>@endif
                                        @if (!$event->starts_at && $dateLabel[0] === '')<p class="kl-event__meta">Date to be announced</p>@endif
                                        <div class="kl-event__foot">
                                            @if (!empty($event->data['location']))
                                                <span class="kl-event__meta">{!! \App\Support\Icon::svg('pin', 'kl-icon kl-icon--sm') !!} {{ $event->data['location'] }}</span>
                                            @endif
                                            @if ($eventLink && \App\Support\SafeUrl::isSafe($eventLink))
                                                <a class="kl-circle-link stretched-link" href="{{ $eventLink }}" @if (str_starts_with($eventLink, 'http')) target="_blank" rel="noopener noreferrer" @endif aria-label="Details for {{ $event->title }}">{!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            </div>
                        @empty
                            {{-- No published events: keep the three card positions with a neutral announcement — no dates, places or details. --}}
                            @if ($preview ?? false)
                                <div class="col-12">@include('public.partials.preview-note', ['message' => 'No upcoming events are published — visitors see the neutral cards below. Add events under Content → Events.'])</div>
                            @endif
                            @for ($i = 0; $i < 3; $i++)
                                <div class="col-md-4">
                                    <article class="kl-event kl-event--empty">
                                        <div class="kl-event__media">
                                            <div class="kl-event__empty-visual" aria-hidden="true">{!! \App\Support\Icon::svg('calendar', 'kl-icon') !!}</div>
                                            <span class="kl-event__date"><strong>TBA</strong>DATE</span>
                                        </div>
                                        <div class="kl-event__body">
                                            <h3 class="kl-card__title">Event announcement coming soon</h3>
                                            <p class="kl-event__meta">Details will be published here.</p>
                                            <div class="kl-event__foot">
                                                <span class="kl-event__meta">{!! \App\Support\Icon::svg('calendar', 'kl-icon kl-icon--sm') !!} Check back soon</span>
                                                <a class="kl-circle-link stretched-link" href="{{ $eventsButtonUrl }}" aria-label="Go to the events page">{!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            @endfor
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
@else
<section class="kl-section" id="upcoming-events">
    <div class="container">
        @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
        @include('public.partials.heading', ['title' => $d['title'] ?? 'Upcoming Events', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--bold mb-4'])
        @if($featuredEvent)
            <article class="featured-event mb-4">
                <div class="row g-0 align-items-stretch">
                    <div class="col-lg-5 featured-event-copy"><p class="kl-kicker text-white-50">Featured Event</p><h3 class="h2 fw-bold">{{ $featuredEvent->title }}</h3><p>{{ $featuredEvent->data['summary'] ?? $featuredEvent->data['description'] ?? '' }}</p><div class="small mb-2">{{ $eventDate($featuredEvent) }}</div>@if(!empty($featuredEvent->data['location']))<div class="small mb-3">{!! \App\Support\Icon::svg('pin', 'kl-icon kl-icon--sm') !!} {{ $featuredEvent->data['location'] }}</div>@endif @if(!empty($featuredEvent->data['registration_url']))<a class="btn btn-warning fw-bold" href="{{ $featuredEvent->data['registration_url'] }}" target="_blank" rel="noopener noreferrer">Register / Details →</a>@elseif(!empty($featuredEvent->data['url']) && ! $isCurrentPage($featuredEvent->data['url']))<a class="btn btn-warning fw-bold" href="{{ $featuredEvent->data['url'] }}">View Details →</a>@endif</div>
                    <div class="col-lg-7">@if(!empty($featuredEvent->data['image']))<x-picture :contents="true" img-class="featured-event-image" :image="$featuredEvent->data['image']" :alt="$featuredEvent->data['image_alt'] ?? $featuredEvent->title" sizes="(min-width: 992px) 70vw, 100vw" :dimensions="true" />@elseif($preview ?? false)<div class="featured-event-image image-placeholder">Add a verified event image</div>@else<div class="featured-event-image image-placeholder" aria-hidden="true"></div>@endif</div>
                </div>
            </article>
        @endif
        <div class="row g-3">
            @forelse($upcomingEvents as $event)
                <div class="col-md-6 col-lg-4"><article class="soft-card event-card h-100">@if(!empty($event->data['image']))<x-picture :contents="true" img-class="event-card-image" :image="$event->data['image']" :alt="$event->data['image_alt'] ?? $event->title" sizes="(min-width: 992px) 40vw, (min-width: 768px) 60vw, 100vw" :dimensions="true" />@elseif($preview ?? false)<div class="event-card-image image-placeholder">Add event image</div>@endif<div class="card-body"><p class="kl-kicker">{{ $eventDate($event) }}</p><h3 class="h5 fw-bold">{{ $event->title }}</h3><p class="small text-secondary">{{ $event->data['summary'] ?? $event->data['description'] ?? '' }}</p>@if(!empty($event->data['location']))<p class="small mb-3">{!! \App\Support\Icon::svg('pin', 'kl-icon kl-icon--sm') !!} {{ $event->data['location'] }}</p>@endif @if(!empty($event->data['registration_url']))<a class="fw-bold text-decoration-none" href="{{ $event->data['registration_url'] }}" target="_blank" rel="noopener noreferrer">Register →</a>@elseif(!empty($event->data['url']) && ! $isCurrentPage($event->data['url']))<a class="fw-bold text-decoration-none" href="{{ $event->data['url'] }}">View details →</a>@endif</div></article></div>
            @empty
                <div class="col-12"><div class="empty-state">New events and activities will be announced here. Please contact KELIKA for current information.</div></div>
            @endforelse
        </div>
        @if(!empty($d['button_label']))<a class="btn btn-kelika mt-4" href="{{ $eventsButtonUrl }}">{{ $d['button_label'] }} →</a>@endif
    </div>
</section>
@endif

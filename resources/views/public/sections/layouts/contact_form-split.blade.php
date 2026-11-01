{{-- Contact form, "split" layout (Contact design): the form on a soft panel beside the location column. The form posts to
     the same route with the same fields, CSRF token, honeypot and validation messages as the default layout; the subject
     can be preselected with ?topic=… (one of the listed topics). The location column takes its heading and text from this
     page's Location section when it uses the "beside" layout, and shows only contact details configured in Site
     Settings: the map and Get Directions only with a map URL, each detail only when it is set. An address of several
     lines starts with the place name, shown in bold. A bare 10-digit Indian number is shown as +91 XXXXX XXXXX like the
     reference; a line of the opening hours in brackets is shown as a note. --}}
@php
    $d = $section->data ?? [];
    $topics = ['Programs Enquiry', 'Court Booking', 'Events & Tournaments', 'Membership', 'Partnerships', 'Other'];
    $topic = old('category', in_array(request()->query('topic'), $topics, true) ? request()->query('topic') : null);
    $location = \App\Support\SectionDefaults::companion($section, 'map_embed');
    $l = $location?->data ?? [];
    $mapUrl = ($l['map_url'] ?? '') ?: ($siteSettings['map_url'] ?? '');
    $mapIsEmbed = \App\Support\SafeUrl::isGoogleMapsEmbed($mapUrl);
    $address = trim((string) ($siteSettings['contact_address'] ?? ''));
    $addressLines = collect(preg_split('/\R/', $address))->map(fn ($line) => trim($line))->filter()->values();
    $directionsUrl = $mapUrl && filter_var($mapUrl, FILTER_VALIDATE_URL) ? $mapUrl : null;
    $phone = trim((string) ($siteSettings['contact_phone'] ?? ''));
    $phoneDial = preg_replace('/[^0-9+]/', '', $phone);
    if (preg_match('/^[6-9]\d{9}$/', $phoneDial)) [$phone, $phoneDial] = ['+91 '.substr($phoneDial, 0, 5).' '.substr($phoneDial, 5), '+91'.$phoneDial];
    $phoneHours = trim((string) ($siteSettings['contact_phone_hours'] ?? ''));
    $emailNote = trim((string) ($siteSettings['contact_email_note'] ?? ''));
    $email = trim((string) ($siteSettings['contact_email'] ?? ''));
    $hours = trim((string) ($siteSettings['opening_hours'] ?? ''));
    $isNote = fn (string $line) => (bool) preg_match('/^\(.*\)$/', $line);
    $hourGroups = collect(preg_split('/\R/', $hours))->map(fn ($line) => trim($line))->filter()->values()
        ->chunkWhile(fn ($line, $key, $chunk) => $isNote($line) === $isNote($chunk->last()));
    $hasDetails = $phone !== '' || $email !== '' || $hours !== '';
@endphp
<section class="kl-section kl-contact" id="contact-form">
    <div class="container">
        <div class="kl-contact__grid">
            <div class="kl-contact__form">
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? 'Get in Touch', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
                @if (!empty($d['subtitle']))<p class="kl-contact__intro">{{ $d['subtitle'] }}</p>@endif

                @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->has('form'))<div class="alert alert-danger" role="alert">{{ $errors->first('form') }}</div>@endif

                <form method="post" action="{{ route('contact.store') }}" class="kl-contact__fields" novalidate>
                    @csrf
                    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
                    <input type="hidden" name="source_page" value="{{ $page->slug ?? 'contact' }}">
                    <div class="kl-contact__field">
                        <label class="form-label" for="contact-name">Full Name <span class="kl-contact__required" aria-hidden="true">*</span></label>
                        <input id="contact-name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name" placeholder="Your name" @error('name') aria-describedby="contact-name-error" @enderror>
                        @error('name')<div class="invalid-feedback d-block" id="contact-name-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="kl-contact__field">
                        <label class="form-label" for="contact-email">Email Address <span class="kl-contact__required" aria-hidden="true">*</span></label>
                        <input id="contact-email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email" placeholder="yourname@email.com" @error('email') aria-describedby="contact-email-error" @enderror>
                        @error('email')<div class="invalid-feedback d-block" id="contact-email-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="kl-contact__field">
                        <label class="form-label" for="contact-phone">Phone Number</label>
                        <input id="contact-phone" type="tel" class="form-control" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel" placeholder="Your phone number">
                    </div>
                    <div class="kl-contact__field">
                        <label class="form-label" for="contact-category">Subject</label>
                        <select id="contact-category" class="form-select" name="category">
                            <option value="">Select a subject</option>
                            @foreach ($topics as $option)<option value="{{ $option }}" @selected($topic === $option)>{{ $option }}</option>@endforeach
                        </select>
                    </div>
                    <div class="kl-contact__field kl-contact__field--wide">
                        <label class="form-label" for="contact-message">Message <span class="kl-contact__required" aria-hidden="true">*</span></label>
                        <textarea id="contact-message" class="form-control @error('message') is-invalid @enderror" name="message" rows="6" maxlength="5000" required placeholder="How can we help you?" data-char-count="contact-message-count" aria-describedby="contact-message-count @error('message') contact-message-error @enderror">{{ old('message') }}</textarea>
                        <div class="kl-contact__count" id="contact-message-count" aria-live="polite">{{ mb_strlen(old('message', '')) }} / 5000</div>
                        @error('message')<div class="invalid-feedback d-block" id="contact-message-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="kl-contact__field kl-contact__field--wide">
                        <div class="form-check">
                            <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" value="1" id="contact-consent" name="consent" required @checked(old('consent'))>
                            <label class="form-check-label small" for="contact-consent">I agree that KELIKA may use my details to respond to this enquiry.</label>
                        </div>
                        @error('consent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="kl-contact__field kl-contact__field--wide">
                        <button class="btn btn-kelika kl-contact__submit" type="submit">{{ ($d['button_label'] ?? '') ?: 'Send Message' }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</button>
                    </div>
                </form>
            </div>

            <div class="kl-contact__location" id="{{ \Illuminate\Support\Str::slug(($l['eyebrow'] ?? '') ?: 'our-location') }}">
                <p class="kl-kicker">{{ ($l['eyebrow'] ?? '') ?: 'Our Location' }}</p>
                @include('public.partials.heading', ['title' => ($l['title'] ?? '') ?: 'Visit KELIKA', 'accent' => $location ? \App\Support\SectionDefaults::accent($location) : 'none', 'class' => 'kl-heading kl-heading--title'])
                @if (!empty($l['body']))<p class="kl-contact__intro">{{ $l['body'] }}</p>@endif

                <div @class(['kl-contact__map', 'kl-contact__map--empty' => ! $mapIsEmbed])>
                    @if ($mapIsEmbed)
                        <iframe title="KELIKA location map" src="{{ $mapUrl }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                    @else
                        <span class="kl-contact__map-icon" aria-hidden="true">{!! \App\Support\Icon::svg('pin', 'kl-icon kl-icon--lg') !!}</span>
                        {{-- The address itself is shown just below the map, so the placeholder does not repeat it. --}}
                        @if ($address === '')<p class="kl-contact__map-text">Location details will be shared here soon. Contact our team for directions.</p>@endif
                        @if ($preview ?? false)
                            @include('public.partials.preview-note', ['message' => 'No map yet — add a Google Maps embed URL in Site Settings (or on the Location section) to show the map and Get Directions.'])
                        @endif
                    @endif
                </div>

                @if ($address !== '' || $directionsUrl)
                    <div class="kl-contact__address">
                        @if ($address !== '')
                            <span class="kl-contact__address-icon" aria-hidden="true">{!! \App\Support\Icon::svg('pin', 'kl-icon') !!}</span>
                            <p class="kl-contact__address-text">@if ($addressLines->count() > 1)<strong class="kl-contact__address-name">{{ $addressLines->first() }}</strong><br>{!! $addressLines->slice(1)->map(fn ($line) => e($line))->implode('<br>') !!}@else{{ $addressLines->first() }}@endif</p>
                        @endif
                        @if ($directionsUrl)
                            <a class="btn btn-kelika-outline kl-contact__directions" href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer">Get Directions {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a>
                        @endif
                    </div>
                @endif

                @if ($hasDetails)
                    <ul class="kl-contact__details">
                        @if ($phone !== '')
                            <li><span class="kl-contact__detail-icon" aria-hidden="true">{!! \App\Support\Icon::svg('phone', 'kl-icon') !!}</span><div><h3 class="kl-contact__detail-title">Call Us</h3><a href="tel:{{ $phoneDial }}">{{ $phone }}</a>@if ($phoneHours !== '')<p class="kl-contact__detail-note">{!! nl2br(e($phoneHours)) !!}</p>@endif</div></li>
                        @endif
                        @if ($email !== '')
                            <li><span class="kl-contact__detail-icon" aria-hidden="true">{!! \App\Support\Icon::svg('mail', 'kl-icon') !!}</span><div><h3 class="kl-contact__detail-title">Email Us</h3><a href="mailto:{{ $email }}">{{ $email }}</a>@if ($emailNote !== '')<p class="kl-contact__detail-note">{!! nl2br(e($emailNote)) !!}</p>@endif</div></li>
                        @endif
                        @if ($hours !== '')
                            <li><span class="kl-contact__detail-icon" aria-hidden="true">{!! \App\Support\Icon::svg('clock', 'kl-icon') !!}</span><div><h3 class="kl-contact__detail-title">Visit Hours</h3>@foreach ($hourGroups as $lines)<p{!! $isNote($lines->first()) ? ' class="kl-contact__detail-note"' : '' !!}>{!! $lines->map(fn ($line) => e($line))->implode('<br>') !!}</p>@endforeach</div></li>
                        @endif
                    </ul>
                @endif
            </div>
        </div>
    </div>
</section>

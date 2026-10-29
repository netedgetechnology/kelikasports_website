@php
    $d = \App\Support\SectionDefaults::apply($section);
    $heroImage = \App\Support\Media::url($d['image'] ?? null);
    $heroMobileImage = \App\Support\Media::url($d['mobile_image'] ?? null);
    $scriptLines = \App\Support\SectionDefaults::lines($d['script_text'] ?? '');
    $valueLines = \App\Support\SectionDefaults::lines($d['values_text'] ?? '');
    $heroItems = array_values(array_filter($d['items'] ?? [], fn ($item) => is_array($item) && trim((string) ($item['title'] ?? '')) !== ''));
    // Items with a value are figures (e.g. "500+" Active Members). Figures marked as demo content (unverified) are shown in
    // CMS preview and development demo mode only, as in the statistics band.
    $heroFiguresAreDemo = ! empty($d['demo_content']) && array_filter($heroItems, fn ($item) => trim((string) ($item['value'] ?? '')) !== '');
    if ($heroFiguresAreDemo && ! ($preview ?? false) && ! ($demoMode ?? false)) $heroItems = array_values(array_filter($heroItems, fn ($item) => trim((string) ($item['value'] ?? '')) === ''));
    $heroHasFigures = (bool) array_filter($heroItems, fn ($item) => trim((string) ($item['value'] ?? '')) !== '');
    // Values without digits (e.g. "Talk to Us") are short titles above a line of text, not figures: kl-hero__features--labels.
    // "cinematic": the photo fills the hero behind the text (homepage); "standard": the photo beside the text.
    $cinematic = \App\Support\SectionDefaults::layout($section) === 'cinematic';
    if ($cinematic) $scriptLines = $valueLines = [];
    // Words wrapped in *asterisks* in the title take the heading accent colour (gold unless set otherwise).
    $highlightAccent = in_array($d['heading_accent'] ?? '', ['terracotta', 'gold', 'green'], true) ? $d['heading_accent'] : 'gold';
    // Without a booking URL, booking goes to the contact form with "Court Booking" chosen as the topic.
    $bookingFallback = route('page.show', ['slug' => 'contact', 'topic' => 'Court Booking']).'#contact-form';
@endphp

@php($titleLines = \App\Support\SectionDefaults::lines($d['title'] ?? 'PLAY|GROW|BELONG.'))
@php($longestTitleLine = max(array_map(fn ($line) => mb_strlen(str_replace('*', '', $line)), $titleLines ?: [''])))
{{-- Cinematic modifiers: a smaller headline for long title lines (over 16 characters), and on phones a frame in the
     photo's own landscape shape when there is no separate phone image, so it is not cropped to a narrow slice. --}}
<section @class(['kl-hero', 'kl-hero--panel' => $scriptLines || $valueLines, 'kl-hero--cinematic' => $cinematic, 'kl-hero--long-title' => $cinematic && $longestTitleLine > 16, 'kl-hero--no-mobile-image' => $cinematic && $heroImage && ! $heroMobileImage])>
    @if ($cinematic)
        <div class="kl-hero__decor" aria-hidden="true"></div>
    @endif
    <div class="kl-hero__copy">
        @if (!empty($d['eyebrow']))
            <p class="kl-kicker">{{ $d['eyebrow'] }}</p>
        @endif

        <h1 class="kl-hero__title">
            @foreach (\App\Support\SectionDefaults::lines($d['title'] ?? 'PLAY|GROW|BELONG.') as $line)
                <span>@foreach (\App\Support\SectionDefaults::highlights($line) as $part)@if ($part['highlight'])<span class="kl-hero__highlight kl-accent-{{ $highlightAccent }}">{{ $part['text'] }}</span>@else{{ $part['text'] }}@endif @endforeach</span>
            @endforeach
        </h1>

        @if (!empty($d['subtitle']))
            <p class="kl-hero__lead">{{ $d['subtitle'] }}</p>
        @endif

        @if (!empty($d['button_label']) || !empty($d['secondary_button_label']))
            <div class="kl-actions">
                @if (!empty($d['button_label']))
                    <a class="btn btn-kelika"
                       href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : route('page.show', ['slug' => 'academy']) }}">
                        {{ $d['button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                    </a>
                @endif

                @if (!empty($d['secondary_button_label']))
                    <a class="btn btn-kelika-outline"
                       href="{{ \App\Support\SafeUrl::isSafe($d['secondary_button_url'] ?? null) && !empty($d['secondary_button_url']) ? $d['secondary_button_url'] : (($siteSettings['booking_url'] ?? '') ?: $bookingFallback) }}"
                       @if (!empty($siteSettings['booking_url']) && empty($d['secondary_button_url'])) target="_blank" rel="noopener noreferrer" @endif>
                        {{ $d['secondary_button_label'] }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                    </a>
                @endif
            </div>
        @endif

        @if ($heroItems)
            <ul @class(['kl-hero__features', 'kl-hero__features--figures' => $heroHasFigures, 'kl-hero__features--labels' => $heroHasFigures && ! array_filter($heroItems, fn ($item) => preg_match('/\d/', (string) ($item['value'] ?? ''))), 'kl-hero__features--five' => count($heroItems) === 5, 'kl-hero__features--six' => count($heroItems) === 6]) aria-label="Highlights">
                @foreach ($heroItems as $item)
                    <li>
                        {!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'], 'kl-icon kl-icon--lg') !!}
                        @if (trim((string) ($item['value'] ?? '')) !== '')
                            <span class="kl-hero__figure"><strong>{{ $item['value'] }}</strong> <span>{{ implode(' ', \App\Support\SectionDefaults::lines($item['title'])) }}</span></span>
                        @else
                        <span>
                            @foreach (\App\Support\SectionDefaults::lines($item['title']) as $line)
                                {{ $line }}@if (!$loop->last)<br>@endif
                            @endforeach
                        </span>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($heroFiguresAreDemo && ($preview ?? false))
                @include('public.partials.preview-note', ['message' => 'Demo figures — unverified and shown only in CMS preview and development demo mode. Untick “Demo content” on this hero once every figure is verified.'])
            @endif
        @endif
    </div>

    <div class="kl-hero__visual">
        @if ($heroImage)
            {{-- Primary image: eager and high priority. The hero box can be drawn as wide as the viewport. --}}
            <x-picture :image="$d['image'] ?? null" :mobile="$d['mobile_image'] ?? null" :alt="$d['image_alt'] ?? ''"
                       img-class="kl-hero__image" :img-style="'--kl-hero-pos: '.($d['image_position'] ?? 'center')"
                       :sizes="$cinematic ? '(min-width: 1200px) 64vw, (min-width: 992px) 70vw, 100vw' : '100vw'"
                       loading="none" fetchpriority="high" :dimensions="true" />
        @elseif ($preview ?? false)
            <div class="kl-hero__image image-placeholder" role="img" aria-label="Hero image to be selected in CMS">Select hero image in CMS</div>
        @else
            <div class="kl-hero__image" aria-hidden="true"></div>
        @endif

        @if ($cinematic)
            {{-- Overlay darkness from the CMS: the default keeps the original overlay exactly; another value scales its stops. --}}
            @php($overlay = \App\Support\SectionDefaults::overlayDarkness($section))
            @if ($overlay === \App\Support\SectionDefaults::OVERLAY_DEFAULT)
                <div class="kl-hero__shade" aria-hidden="true"></div>
            @else
                <div class="kl-hero__shade kl-hero__shade--custom" aria-hidden="true" style="--kl-shade: {{ round($overlay / \App\Support\SectionDefaults::OVERLAY_DEFAULT, 4) }}" data-overlay="{{ $overlay }}"></div>
            @endif
        @endif

        @if ($scriptLines || $valueLines)
            <div class="kl-hero__panel">
                @if ($scriptLines)
                    <p class="kl-script">
                        @foreach ($scriptLines as $line)
                            {{ $line }}@if (!$loop->last)<br>@endif
                        @endforeach
                    </p>
                @endif
                @if ($valueLines)
                    <ul class="kl-hero__values">
                        @foreach ($valueLines as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>
</section>

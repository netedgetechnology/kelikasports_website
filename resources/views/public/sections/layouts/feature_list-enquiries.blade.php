{{-- Feature list, "enquiries" layout (Contact design, "How Can We Help You?"): quick enquiry cards (icon, title, link) on
     the left and this page's FAQ section, when it uses the "beside" layout, as an accordion on the right. A card's link is
     usually the contact form with its subject preselected, e.g. /contact?topic=Court%20Booking#contact-form. --}}
@php
    $faqSection = \App\Support\SectionDefaults::companion($section, 'faq');
    $f = $faqSection?->data ?? [];
    $faqItems = array_values(array_filter($f['items'] ?? [], fn ($item) => is_array($item) && trim((string) ($item['question'] ?? '')) !== ''));
    $cardColors = ['#1f8a52', '#d4652e', '#c98a33', '#c98a33', '#2b6f8e', '#2b6f8e'];
@endphp
<section @class(['kl-section', 'kl-help', 'kl-help--single' => ! $faqSection]) id="{{ \Illuminate\Support\Str::slug(($d['eyebrow'] ?? '') ?: 'quick-enquiries') }}">
    <div class="container">
        <div class="kl-help__grid">
            <div class="kl-help__enquiries">
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
                @if (!empty($d['body']))<p class="kl-help__intro">{{ $d['body'] }}</p>@endif
                <ul class="kl-help__cards">
                    @foreach (($d['items'] ?? []) as $item)
                        @continue(trim((string) ($item['title'] ?? '')) === '')
                        @php
                            $itemColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : $cardColors[$loop->index % count($cardColors)];
                            $cardUrl = \App\Support\SafeUrl::isSafe($item['url'] ?? null) && !empty($item['url']) ? $item['url'] : null;
                        @endphp
                        <li class="kl-help-card" style="--kl-item: {{ $itemColor }}">
                            <span class="kl-help-card__icon">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'], 'kl-icon kl-icon--xl') ?: \App\Support\Icon::svg('chat', 'kl-icon kl-icon--xl') !!}</span>
                            <h3 class="kl-help-card__title">
                                @if ($cardUrl)<a class="stretched-link" href="{{ $cardUrl }}">@endif
                                @foreach (\App\Support\SectionDefaults::lines($item['title']) as $line){{ $line }}@if (!$loop->last)<br>@endif @endforeach
                                @if ($cardUrl)</a>@endif
                            </h3>
                            @if ($cardUrl)<span class="kl-help-card__arrow" aria-hidden="true">{!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </div>

            @if ($faqSection)
                <div class="kl-help__faq" id="{{ \Illuminate\Support\Str::slug(($f['eyebrow'] ?? '') ?: 'faq') }}">
                    @if (!empty($f['eyebrow']))<p class="kl-kicker">{{ $f['eyebrow'] }}</p>@endif
                    @include('public.partials.heading', ['title' => $f['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($faqSection), 'class' => 'kl-heading kl-heading--title'])
                    @if (!empty($f['subtitle']))<p class="kl-help__intro">{{ $f['subtitle'] }}</p>@endif
                    <div class="kl-faq">
                        @foreach ($faqItems as $item)
                            <details class="kl-faq__item">
                                <summary class="kl-faq__question"><span>{{ $item['question'] }}</span>{!! \App\Support\Icon::svg('chevron-down', 'kl-icon kl-icon--sm kl-faq__chevron') !!}</summary>
                                @if (!empty($item['answer']))<p class="kl-faq__answer">{{ $item['answer'] }}</p>@endif
                            </details>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>

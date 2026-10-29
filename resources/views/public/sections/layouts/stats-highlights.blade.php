{{-- Statistics, "highlights" layout (About design): a light band of short highlights, each a value (e.g. "8+",
     "All Age Groups") above a small label with a gold rule. Highlights marked as demo content (unverified) are shown in CMS
     preview and development demo mode only; other visitors see no band at all until they are verified. --}}
@php
    $d = $section->data ?? [];
    $highlightItems = array_values(array_filter($d['items'] ?? [], fn ($item) => is_array($item) && trim((string) ($item['value'] ?? '')) !== ''));
    $highlightsAreDemo = ! empty($d['demo_content']) && $highlightItems;
    if ($highlightsAreDemo && ! ($preview ?? false) && ! ($demoMode ?? false)) $highlightItems = [];
@endphp
@if ($highlightItems || ($preview ?? false))
<section class="kl-highlights" aria-label="{{ ($d['eyebrow'] ?? '') ?: 'KELIKA at a glance' }}">
    <div class="container">
        @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => 'none', 'class' => 'visually-hidden'])
        @if ($highlightsAreDemo && ($preview ?? false))
            @include('public.partials.preview-note', ['message' => 'Demo highlights — unverified and shown only in CMS preview and development demo mode. Visitors see no band until “Demo content” is unticked on this section.'])
        @elseif (! $highlightItems)
            @include('public.partials.preview-note', ['message' => 'No highlights yet — this band is hidden from visitors. Add items (value and label) to this section.'])
        @endif
        @if ($highlightItems)
            <ul class="kl-highlights__list">
                @foreach ($highlightItems as $item)
                    <li class="kl-highlight">
                        <strong class="kl-highlight__value">{{ $item['value'] }}</strong>
                        @if (!empty($item['label']))<span class="kl-highlight__label">{{ $item['label'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
@endif

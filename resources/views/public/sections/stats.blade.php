@if (\App\Support\SectionDefaults::layout($section) === 'highlights')
@include('public.sections.layouts.stats-highlights')
@else
@php
    $d = $section->data ?? [];
    $statItems = array_values(array_filter($d['items'] ?? [], fn ($item) => is_array($item) && trim((string) ($item['value'] ?? '')) !== ''));
    $statImage = \App\Support\Media::url($d['image'] ?? null);
    // Statistics marked as demo content (unverified) are shown in CMS preview and development demo mode; elsewhere visitors see the neutral band.
    $statsAreDemo = ! empty($d['demo_content']) && $statItems;
    if ($statsAreDemo && ! ($preview ?? false) && ! ($demoMode ?? false)) $statItems = [];
@endphp
{{-- Always rendered. Without verified CMS statistics the band shows neutral "coming soon" columns — never numbers. --}}
<section class="kl-stats kl-stats--image" aria-label="{{ ($d['eyebrow'] ?? '') ?: 'KELIKA at a glance' }}">
    @if ($statImage)
        <x-picture :contents="true" img-class="kl-stats__image" :image="$d['image'] ?? null" :alt="$d['image_alt'] ?? ''"
                   :img-style="'object-position:'.($d['image_position'] ?? 'center')" sizes="(min-width: 768px) 45vw, 100vw" :dimensions="true" />
    @else
        @include('public.partials.decor-shuttlecock')
    @endif
    <div class="container">
        @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => 'none', 'class' => 'visually-hidden'])
        @if ($statsAreDemo && ($preview ?? false))
            @include('public.partials.preview-note', ['message' => 'Demo statistics — unverified and shown only in CMS preview. Visitors see the neutral band until “Demo content” is unticked on this section.'])
        @elseif (! $statItems && ($preview ?? false))
            @include('public.partials.preview-note', ['message' => 'No verified statistics yet — visitors see the neutral “coming soon” band below. Add figures to this section in the CMS.'])
        @endif
        <ul class="kl-stats__list">
            @forelse ($statItems as $item)
                <li class="kl-stat">
                    <strong class="kl-stat__value">{{ $item['value'] }}</strong>
                    <span class="kl-stat__label">{{ $item['label'] ?? '' }}</span>
                </li>
            @empty
                @for ($i = 0; $i < 4; $i++)
                    <li class="kl-stat kl-stat--empty">
                        <strong class="kl-stat__value" aria-hidden="true">—</strong>
                        <span class="kl-stat__label">Coming soon</span>
                    </li>
                @endfor
            @endforelse
        </ul>
    </div>
</section>
@endif

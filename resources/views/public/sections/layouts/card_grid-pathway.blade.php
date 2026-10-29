{{-- Card grid, "pathway" layout (Programs design, "A Clear Pathway for Growth"): heading beside the intro text, then one
     image card per stage with an arrow between consecutive cards. Cards without their own image reuse the image of the
     program record with the same title, as in the default grid. The "spaces" layout (Facilities design) includes this
     template with $stageArrows off and $stageIcons on: a card without an image then shows its icon instead. --}}
@php
    $stages = array_values(array_filter($d['items'] ?? [], fn ($item) => is_array($item) && trim((string) ($item['title'] ?? '')) !== ''));
@endphp
<section @class(['kl-section', 'kl-pathway', 'kl-pathway--spaces' => $stageIcons ?? false]) id="{{ \Illuminate\Support\Str::slug(($d['eyebrow'] ?? '') ?: 'training-structure') }}">
    <div class="container">
        <div class="kl-split-head">
            <div>
                @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
            </div>
            @if (!empty($d['subtitle']) || !empty($d['body']))<p class="kl-split-head__text">{{ ($d['subtitle'] ?? '') ?: $d['body'] }}</p>@endif
        </div>

        <ol class="kl-pathway__list">
            @foreach ($stages as $item)
                @php
                    $record = $records->get(strtolower(trim($item['title'])));
                    $stageImage = ($item['image'] ?? '') ?: ($record?->data['image'] ?? null);
                    $stageUrl = \App\Support\SafeUrl::isSafe($item['url'] ?? null) && !empty($item['url']) ? $item['url'] : null;
                @endphp
                <li @class(['kl-stage', 'kl-stage--icon' => ! $stageImage && ($stageIcons ?? false)])>
                    <div class="kl-stage__frame">
                    <div class="kl-stage__media">
                        @if ($stageImage)
                            <x-picture :image="$stageImage" :mobile="($item['image'] ?? '') ? ($item['mobile_image'] ?? null) : null" :alt="$item['image_alt'] ?? ''"
                                       :img-style="'object-position:'.($item['image_position'] ?? 'center')" sizes="(min-width: 1200px) 320px, (min-width: 576px) 46vw, 100vw" mobile-sizes="100vw" />
                        @elseif ($stageIcons ?? false)
                            <span class="kl-stage__icon" style="--kl-item: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : 'var(--kelika-green)' }}">{!! \App\Support\Icon::render($item['icon'] ?? null, $item['title'], 'kl-icon kl-icon--xl') ?: \App\Support\Icon::svg('grid', 'kl-icon kl-icon--xl') !!}</span>
                        @elseif ($preview ?? false)
                            <div class="image-placeholder">Select an image in the CMS</div>
                        @endif
                    </div>
                    @if (! $loop->last && ($stageArrows ?? true))
                        <span class="kl-stage__arrow" aria-hidden="true">{!! \App\Support\Icon::svg('arrow-right', 'kl-icon') !!}</span>
                    @endif
                    </div>
                    <div class="kl-stage__body">
                        <h3 class="kl-stage__title">@if ($stageUrl)<a class="stretched-link" href="{{ $stageUrl }}">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif</h3>
                        @if (!empty($item['description']))<p class="kl-stage__text">{{ $item['description'] }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>

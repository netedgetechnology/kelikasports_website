{{-- Facilities showcase, "amenities" layout (Facilities design, "Modern Amenities"): the section photo running to the
     viewport's left edge, then heading, text and the published Facility records as icon features (icon, title, and the
     record's description when it has one). Without a photo the copy spans the width. --}}
@php
    $amenityImage = \App\Support\Media::url($d['image'] ?? null);
    $showMedia = $amenityImage || ($preview ?? false);
    $amenityColors = ['#c98a33', '#d4652e', '#1f8a52'];
@endphp
<section @class(['kl-section', 'kl-amenities-band', 'kl-amenities-band--no-media' => ! $showMedia]) id="facility-list">
    @if ($showMedia)
        <div class="kl-amenities-band__media">
            @if ($amenityImage)
                <x-picture :image="$d['image'] ?? null" :mobile="$d['mobile_image'] ?? null" :alt="$d['image_alt'] ?? ''"
                           :img-style="'object-position:'.($d['image_position'] ?? 'center')" sizes="(min-width: 992px) 46vw, 100vw" :dimensions="true" />
            @else
                <div class="image-placeholder">Select an image in the CMS</div>
            @endif
        </div>
    @endif
    <div class="kl-amenities-band__copy">
        @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
        @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
        @if (!empty($d['subtitle']))<p class="kl-amenities-band__text">{{ $d['subtitle'] }}</p>@endif
        <ul class="kl-amenities-band__list">
            @forelse (($facilities ?? collect()) as $item)
                @php($itemColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item->data['color'] ?? '')) ? $item->data['color'] : $amenityColors[$loop->index % count($amenityColors)])
                <li class="kl-amenity" style="--kl-item: {{ $itemColor }}">
                    <span class="kl-amenity__icon">{!! \App\Support\Icon::render($item->data['icon'] ?? null, $item->title, 'kl-icon kl-icon--lg') ?: \App\Support\Icon::svg('check', 'kl-icon kl-icon--lg') !!}</span>
                    <div>
                        <h3 class="kl-amenity__title">{{ $item->title }}</h3>
                        @if (!empty($item->data['description']))<p class="kl-amenity__text">{{ $item->data['description'] }}</p>@endif
                    </div>
                </li>
            @empty
                @if ($preview ?? false)
                    <li class="empty-state">Facilities will appear here after they are added and published in the CMS.</li>
                @endif
            @endforelse
        </ul>
    </div>
</section>

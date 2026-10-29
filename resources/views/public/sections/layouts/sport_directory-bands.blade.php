{{-- Sport directory, "bands" layout (Sports design, design-reference/Kelika Sports - Sports Page.png): one full-width band
     per published sport — number, eyebrow, name, tagline, description and button; the sport's photo; and its features
     on a side panel (green for the first sport, light for the others). Copy and photo swap sides on even bands. The
     section heading stays for screen readers only, as the design shows none. --}}
@php
    $enquiryUrl = route('page.show', ['slug' => 'contact', 'topic' => 'Programs Enquiry']).'#contact-form';
    $featureColors = ['var(--element-fire)', 'var(--element-water)', 'var(--element-earth)', 'var(--kelika-terracotta)', 'var(--element-space)'];
@endphp
<section class="kl-sports" id="sports-list">
    @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => 'none', 'class' => 'visually-hidden'])
    @forelse ($sports as $item)
        @php
            $features = array_values(array_filter($item->data['features'] ?? [], fn ($feature) => is_array($feature) && trim((string) ($feature['title'] ?? '')) !== ''));
            $sportUrl = \App\Support\SafeUrl::isSafe($item->data['url'] ?? null) && !empty($item->data['url']) ? $item->data['url'] : null;
            $buttonLabel = ($item->data['button_label'] ?? '') ?: ($sportUrl ? 'Explore '.$item->title : 'Enquire About '.$item->title);
        @endphp
        <article @class(['kl-sport', 'kl-sport--reverse' => $loop->even, 'kl-sport--featured' => $loop->first, 'kl-sport--no-features' => ! $features])>
            <div class="kl-sport__copy">
                <span class="kl-sport__number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <div>
                    <p class="kl-kicker">{{ ($item->data['eyebrow'] ?? '') ?: ($loop->first ? 'FEATURED SPORT' : 'OUR SPORT') }}</p>
                    <h3 class="kl-sport__title">{{ $item->title }}</h3>
                    @if (!empty($item->data['tagline']))<p class="kl-sport__tagline">{{ $item->data['tagline'] }}</p>@endif
                    @if (!empty($item->data['description']))<p class="kl-sport__text">{{ $item->data['description'] }}</p>@endif
                    <a class="btn {{ $loop->first ? 'btn-kelika' : 'btn-kelika-outline' }}" href="{{ $sportUrl ?? $enquiryUrl }}">
                        {{ $buttonLabel }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}
                    </a>
                </div>
            </div>
            <div class="kl-sport__media">
                @if (!empty($item->data['image']))
                    <x-picture :image="$item->data['image']" :mobile="$item->data['mobile_image'] ?? null" :alt="$item->data['image_alt'] ?? $item->title"
                               :img-style="'object-position:'.($item->data['image_position'] ?? 'center')" sizes="(min-width: 992px) 36vw, (min-width: 768px) 50vw, 100vw" mobile-sizes="100vw" />
                @elseif ($preview ?? false)
                    <div class="image-placeholder">{{ $item->title }} image to be selected</div>
                @endif
            </div>
            @if ($features)
                <ul class="kl-sport__panel">
                    @foreach ($features as $feature)
                        <li class="kl-sport__feature" style="--kl-item: {{ $featureColors[$loop->index % count($featureColors)] }}">
                            <span class="kl-sport__icon">{!! \App\Support\Icon::render($feature['icon'] ?? null, $feature['title'], 'kl-icon kl-icon--lg') ?: \App\Support\Icon::svg('check', 'kl-icon kl-icon--lg') !!}</span>
                            <div>
                                <h4 class="kl-sport__feature-title">{{ $feature['title'] }}</h4>
                                @if (!empty($feature['description']))<p class="kl-sport__feature-text">{{ $feature['description'] }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </article>
    @empty
        @if ($preview ?? false)<div class="container"><div class="empty-state">Sports will appear here after the approved sports content and images are added in the CMS.</div></div>@endif
    @endforelse
</section>

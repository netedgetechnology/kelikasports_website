{{-- Program list, "cards" layout (Programs design): one photo card per published program with its title and age range,
     the first card highlighted in green. The heading is optional (the approved design shows the cards on their own).
     A card links to the program's URL; without one it opens the contact form on a programs enquiry. --}}
@php
    $enquiryUrl = route('page.show', ['slug' => 'contact', 'topic' => 'Programs Enquiry']).'#contact-form';
@endphp
<section class="kl-section kl-program-cards" id="program-list">
    <div class="container">
        @if (!empty($d['eyebrow']) || !empty($d['title']))
            <div class="kl-split-head">
                <div>
                    @if (!empty($d['eyebrow']))<p class="kl-kicker">{{ $d['eyebrow'] }}</p>@endif
                    @include('public.partials.heading', ['title' => $d['title'] ?? '', 'accent' => \App\Support\SectionDefaults::accent($section), 'class' => 'kl-heading kl-heading--title'])
                </div>
                @if (!empty($d['subtitle']))<p class="kl-split-head__text">{{ $d['subtitle'] }}</p>@endif
            </div>
        @else
            <h2 class="visually-hidden">Our programs</h2>
        @endif

        <ul class="kl-program-cards__grid">
            @forelse ($programs as $item)
                @php($programUrl = \App\Support\SafeUrl::isSafe($item->data['url'] ?? null) && !empty($item->data['url']) ? $item->data['url'] : $enquiryUrl)
                <li @class(['kl-program-card', 'kl-program-card--featured' => $loop->first])>
                    <div class="kl-program-card__media">
                        @if (!empty($item->data['image']))
                            <x-picture :image="$item->data['image']" :mobile="$item->data['mobile_image'] ?? null" :alt="$item->data['image_alt'] ?? $item->title"
                                       :img-style="'object-position:'.($item->data['image_position'] ?? 'center')" sizes="(min-width: 1200px) 320px, (min-width: 576px) 46vw, 100vw" mobile-sizes="100vw" />
                        @elseif ($preview ?? false)
                            <div class="image-placeholder">Select program image in CMS</div>
                        @endif
                    </div>
                    <div class="kl-program-card__body">
                        <h3 class="kl-program-card__title"><a class="stretched-link" href="{{ $programUrl }}">{{ $item->title }}</a></h3>
                        @if (!empty($item->data['age_range']))<p class="kl-program-card__meta">{{ $item->data['age_range'] }}</p>@endif
                    </div>
                </li>
            @empty
                @if ($preview ?? false)
                    <li class="empty-state">Programs will appear here after they are added and published in the CMS.</li>
                @endif
            @endforelse
        </ul>
    </div>
</section>

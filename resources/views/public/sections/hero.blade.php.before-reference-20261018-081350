@php($d = $section->data ?? [])

<section class="hero-section hero-redesign">
    <div class="container-fluid px-4 px-xl-5 position-relative">
        <div class="row align-items-stretch g-0">

            <div class="col-lg-5 hero-copy">
                <p class="eyebrow">{{ $d['eyebrow'] ?? 'DISCIPLINE | HEALTH | COMMUNITY' }}</p>

                <h1 class="display-title">
                    @foreach (explode('|', (string) ($d['title'] ?? 'PLAY|GROW|BELONG.')) as $line)
                        <span class="{{ $loop->index === 1 ? 'accent-terracotta' : ($loop->index === 2 ? 'accent-gold' : '') }}">{{ trim($line) }}</span>

                    @endforeach
                </h1>

                @if (!empty($d['subtitle']))
                    <p class="lead mt-4 mb-4">{{ $d['subtitle'] }}</p>
                @else
                    <p class="lead mt-4 mb-4">World-class badminton training, modern facilities and a vibrant sports community for all ages.</p>
                @endif

                <div class="hero-actions">
                    @if (!empty($d['button_label']))
                        <a class="btn btn-kelika"
                           href="{{ \App\Support\SafeUrl::isSafe($d['button_url'] ?? null) && !empty($d['button_url']) ? $d['button_url'] : route('page.show', ['slug' => 'academy']) }}">
                            {{ $d['button_label'] }} <span aria-hidden="true">→</span>
                        </a>
                    @endif

                    @if (!empty($d['secondary_button_label']))
                        <a class="btn btn-kelika-outline"
                           href="{{ \App\Support\SafeUrl::isSafe($d['secondary_button_url'] ?? null) && !empty($d['secondary_button_url']) ? $d['secondary_button_url'] : (($siteSettings['booking_url'] ?? '') ?: route('page.show', ['slug' => 'contact'])) }}"
                           @if (!empty($siteSettings['booking_url']) && empty($d['secondary_button_url'])) target="_blank" rel="noopener noreferrer" @endif>
                            {{ $d['secondary_button_label'] }} <span aria-hidden="true">→</span>
                        </a>
                    @endif
                </div>

                <div class="hero-features" aria-label="Kelika highlights">
                    <div class="hero-feature">
                        <svg viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="8" r="4"/><path d="M7 26v-3a9 9 0 0 1 18 0v3M11 17l-4 4m14-4 4 4M16 12v7"/></svg>
                        <span>Professional<br>Coaching</span>
                    </div>
                    <div class="hero-feature">
                        <svg viewBox="0 0 32 32" aria-hidden="true"><rect x="3" y="3" width="26" height="26" rx="2"/><path d="M11 3v26M21 3v26M3 11h26M3 21h26M7 7l18 18M25 7 7 25"/></svg>
                        <span>World-class<br>Facilities</span>
                    </div>
                    <div class="hero-feature">
                        <svg viewBox="0 0 32 32" aria-hidden="true"><circle cx="11" cy="9" r="4"/><circle cx="22" cy="10" r="3.5"/><path d="M3 26v-3a8 8 0 0 1 16 0v3M19 18a7 7 0 0 1 10 6v2"/></svg>
                        <span>Programs<br>for All Ages</span>
                    </div>
                    <div class="hero-feature">
                        <svg viewBox="0 0 32 32" aria-hidden="true"><path d="m16 3 4 8 9 1-6.5 6 2 9L16 22l-8.5 5 2-9L3 12l9-1z"/><path d="M12 13h8M16 9v8"/></svg>
                        <span>Tournaments<br>&amp; Events</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 hero-visual">
                @if (!empty($d['image']))
                    <picture>
                        @if (!empty($d['mobile_image']))
                            <source media="(max-width: 767px)"
                                srcset="{{ str_starts_with($d['mobile_image'], 'http') || str_starts_with($d['mobile_image'], '/') ? $d['mobile_image'] : \Illuminate\Support\Facades\Storage::disk('public')->url($d['mobile_image']) }}">
                        @endif
                        <img class="hero-image"
                            src="{{ str_starts_with($d['image'], 'http') || str_starts_with($d['image'], '/') ? $d['image'] : \Illuminate\Support\Facades\Storage::disk('public')->url($d['image']) }}"
                            alt="{{ $d['image_alt'] ?? 'Badminton player training on court' }}"
                            style="object-position:{{ $d['image_position'] ?? 'center' }}"
                            fetchpriority="high">
                    </picture>
                @elseif ($preview ?? false)
                    <div class="hero-image image-placeholder" role="img" aria-label="Hero image to be selected in CMS">Select hero image in CMS</div>
                @else
                    <div class="hero-image" role="img" aria-label="Hero image"></div>
                @endif

                <div class="hero-side-message" aria-label="More than a sport">
                    <span>More<br>Than<br>A Sport</span>
                    <div class="hero-side-values">TRAIN<br>COMPETE<br>GROW<br>BELONG</div>
                    <i></i>
                </div>
            </div>

        </div>
    </div>
</section>

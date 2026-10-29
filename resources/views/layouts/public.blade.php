<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $siteSettings['site_name'] ?? 'KELIKA')</title>
    <meta name="description" content="{{ $page->seo_description ?? ($siteSettings['site_tagline'] ?? 'Play. Grow. Belong. KELIKA sports academy.') }}">
    @php($meta = \App\Support\PageMeta::for($page ?? null, $siteSettings ?? []))
    @if($meta['canonical'])<link rel="canonical" href="{{ $meta['canonical'] }}">@endif
    @if(($preview ?? false) || ($demoMode ?? false))<meta name="robots" content="noindex, nofollow">@elseif(isset($page) && $page->robots_directive)<meta name="robots" content="{{ $page->robots_directive }}">@endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $meta['site_name'] }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    @if($meta['url'])<meta property="og:url" content="{{ $meta['url'] }}">@endif
    @if($meta['image'])<meta property="og:image" content="{{ $meta['image'] }}">@endif
    <meta name="twitter:card" content="{{ $meta['card'] }}">
    <meta name="twitter:title" content="{{ $meta['title'] }}">
    <meta name="twitter:description" content="{{ $meta['description'] }}">
    @if($meta['image'])<meta name="twitter:image" content="{{ $meta['image'] }}">@endif
    @if(!empty($siteSettings['favicon_url']))<link rel="icon" href="{{ str_starts_with($siteSettings['favicon_url'],'http') || str_starts_with($siteSettings['favicon_url'],'/') ? $siteSettings['favicon_url'] : Storage::disk('public')->url($siteSettings['favicon_url']) }}">@endif
    {{-- Branding values arrive already validated, with defaults for anything missing (App\Support\Branding). --}}
    <style>:root{--kelika-green:{{ $siteSettings['primary_color'] }};--kelika-terracotta:{{ $siteSettings['terracotta_color'] }};--kelika-gold:{{ $siteSettings['gold_color'] }};--element-earth:{{ $siteSettings['earth_color'] }};--element-water:{{ $siteSettings['water_color'] }};--element-fire:{{ $siteSettings['fire_color'] }};--element-air:{{ $siteSettings['air_color'] }};--element-space:{{ $siteSettings['space_color'] }};--kelika-font:'{{ $siteSettings['font_family'] }}';--kelika-button-radius:{{ ['soft' => '8px', 'rounded' => '999px'][$siteSettings['button_style']] ?? '3px' }}}</style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@500;600;700;800&family=Mr+Dafoe&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css','resources/js/app.js'])
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link href="{{ asset('css/kelika.css') }}?v={{ filemtime(public_path('css/kelika.css')) }}" rel="stylesheet">
        <link href="{{ asset('css/kelika-home.css') }}?v={{ filemtime(public_path('css/kelika-home.css')) }}" rel="stylesheet">
    @endif
    @stack('head')
</head>
<body>
@if($demoMode ?? false)<div class="kl-demo-banner" role="note"><strong>Development site</strong> · Events, testimonials and figures shown here are unverified demo content for design review.</div>@endif
@if($preview ?? false)<div class="alert alert-warning text-center rounded-0 mb-0"><strong>Preview mode</strong> — this page may contain unpublished changes. <a href="{{ route('admin.pages.edit',$page) }}">Return to CMS</a> · <a href="{{ route('admin.preview.exit') }}">Exit preview</a></div>@endif
<header class="site-header">
    <nav class="navbar navbar-expand-lg container py-2" aria-label="Main navigation">
        <a class="navbar-brand" href="{{ route('home') }}" aria-label="KELIKA home">
            @php($publicLogos = \App\Support\PublicLogos::resolve($siteSettings))@if($publicLogos['header'])<x-picture :image="$publicLogos['header']" :mobile="$publicLogos['mobile']" :alt="$siteSettings['site_name'] ?? 'KELIKA'" img-style="max-height:42px;width:auto" sizes="230px" mobile-sizes="230px" loading="none" />@else{{ $siteSettings['site_name'] ?? 'KELIKA' }}@endif
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                
                @php($mainMenu = $publicMenus->get('main'))
                @if($mainMenu && $mainMenu->items->count())
                    @foreach($mainMenu->items as $item)
                        @if($item->children->isNotEmpty())
                            <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="{{ $item->url }}" id="menu-item-{{ $item->id }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">{{ $item->label }}</a><ul class="dropdown-menu" aria-labelledby="menu-item-{{ $item->id }}">@foreach($item->children as $child)<li><a class="dropdown-item" href="{{ $child->url }}" target="{{ $child->target }}" @if($child->target==='_blank') rel="noopener noreferrer" @endif>{{ $child->label }}</a></li>@endforeach</ul></li>
                        @else
                            <li class="nav-item"><a class="nav-link {{ request()->path() === ltrim(parse_url($item->url, PHP_URL_PATH) ?? '', '/') ? 'active' : '' }}" href="{{ $item->url }}" target="{{ $item->target }}" @if($item->target === '_blank') rel="noopener noreferrer" @endif>{{ $item->label }}</a></li>
                        @endif
                    @endforeach
                @endif
                <li class="nav-item"><a class="nav-link kl-nav-search" href="{{ route('search') }}" aria-label="Search">{!! \App\Support\Icon::svg('search', 'kl-icon') !!}<span class="d-lg-none ms-2">Search</span></a></li>
                <li class="nav-item ms-lg-2"><a class="btn btn-kelika" href="{{ ($siteSettings['booking_url'] ?? '') ?: route('page.show',['slug'=>'contact']) }}" @if(!empty($siteSettings['booking_url'])) target="_blank" rel="noopener noreferrer" @endif>{{ $siteSettings['booking_button_label'] ?? 'Book a Court' }} {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a></li>
            </ul>
        </div>
    </nav>
</header>
<main>@yield('content')</main>
<footer class="site-footer kl-footer">
    <div class="container">
        <div class="kl-footer__columns">
            <div class="kl-footer__about">
                <a class="kl-footer__brand" href="{{ route('home') }}">@php($publicLogos ??= \App\Support\PublicLogos::resolve($siteSettings))@if($publicLogos['footer'])<x-picture :contents="true" :image="$publicLogos['footer']" :alt="$siteSettings['site_name'] ?? 'KELIKA'" sizes="280px" loading="none" />@else{{ $siteSettings['site_name'] ?? 'KELIKA' }}@endif</a>
                @if(!empty($siteSettings['site_tagline']))<p class="kl-footer__tagline">{{ $siteSettings['site_tagline'] }}</p>@endif
                {{-- Only profiles set in Global Settings, and only web addresses, are linked. --}}
                @php($socialLinks = collect(['facebook' => ['facebook_url', 'Facebook'], 'instagram' => ['instagram_url', 'Instagram'], 'youtube' => ['youtube_url', 'YouTube'], 'linkedin' => ['linkedin_url', 'LinkedIn'], 'x' => ['x_url', 'X']])->filter(fn ($social) => is_string($siteSettings[$social[0]] ?? null) && preg_match('#^https?://#i', $siteSettings[$social[0]])))
                @if($socialLinks->isNotEmpty())
                    <ul class="kl-footer__social" aria-label="KELIKA on social media">
                        @foreach($socialLinks as $network => [$key, $label])<li><a class="kl-footer__social-link kl-footer__social-link--{{ $network }}" href="{{ $siteSettings[$key] }}" target="_blank" rel="noopener noreferrer" aria-label="KELIKA on {{ $label }}">{!! \App\Support\Icon::svg($network, 'kl-icon') !!}</a></li>@endforeach
                    </ul>
                @endif
            </div>
            <div class="kl-footer__col kl-footer__col--links"><h2 class="footer-heading">Quick Links</h2>
                @php($footerMenu = $publicMenus->get('footer'))
                @if ($footerMenu)
                    @foreach ($footerMenu->items as $item)
                        <a class="footer-link" href="{{ $item->url }}" target="{{ $item->target }}"
                           @if ($item->target === '_blank') rel="noopener noreferrer" @endif>
                            {{ $item->label }}
                        </a>

                        @foreach ($item->children as $child)
                            <a class="footer-link ms-3 small" href="{{ $child->url }}" target="{{ $child->target }}"
                               @if ($child->target === '_blank') rel="noopener noreferrer" @endif>
                                ↳ {{ $child->label }}
                            </a>
                        @endforeach
                    @endforeach
                @endif
            </div>
            <div class="kl-footer__col kl-footer__col--sports"><h2 class="footer-heading">Our Sports</h2>
                @foreach(($footerSports ?? collect()) as $sport)<a class="footer-link" href="{{ $sport->data['url'] ?? route('page.show',['slug'=>'sports']) }}">{{ $sport->title }}</a>@endforeach
                <a class="footer-link" href="{{ route('page.show',['slug'=>'sports']) }}">More Sports</a>
            </div>
            <div class="kl-footer__col kl-footer__col--contact"><h2 class="footer-heading">Contact</h2>
                <ul class="kl-footer__contact">
                    @if(!empty($siteSettings['contact_phone']))<li><span class="kl-footer__contact-icon">{!! \App\Support\Icon::svg('phone', 'kl-icon') !!}</span><a href="tel:{{ preg_replace('/[^0-9+]/','',$siteSettings['contact_phone']) }}">{{ $siteSettings['contact_phone'] }}</a></li>@endif
                    @if(!empty($siteSettings['contact_email']))<li><span class="kl-footer__contact-icon">{!! \App\Support\Icon::svg('mail', 'kl-icon') !!}</span><a href="mailto:{{ $siteSettings['contact_email'] }}">{{ $siteSettings['contact_email'] }}</a></li>@endif
                    @if(!empty($siteSettings['contact_address']))<li><span class="kl-footer__contact-icon">{!! \App\Support\Icon::svg('pin', 'kl-icon') !!}</span><span>{!! nl2br(e($siteSettings['contact_address'])) !!}</span></li>@endif
                </ul>
                @if (!empty($siteSettings['opening_hours']))<p class="kl-footer__hours">{!! nl2br(e($siteSettings['opening_hours'])) !!}</p>@endif
                <a class="kl-link-arrow kl-footer__contact-link" href="{{ route('page.show',['slug'=>'contact']) }}"><span class="kl-footer__contact-label">Contact us</span> {!! \App\Support\Icon::svg('arrow-right', 'kl-icon kl-icon--sm') !!}</a>
            </div>
        </div>
        <div class="footer-bottom kl-footer__bottom">
            <span>© {{ now()->year }} {{ $siteSettings['site_name'] ?? 'KELIKA' }}. All rights reserved.</span>
            <ul class="kl-footer__legal">
                <li>Play. Grow. Belong.</li>
                {{-- Legal pages are linked only once they exist as published pages (see App\Support\PublicSiteData). --}}
                @if(!empty($footerLegal['terms']))<li><a href="{{ route('page.show', ['slug' => $footerLegal['terms']]) }}">Terms of Service</a></li>@endif
                @if(!empty($footerLegal['privacy']))<li><a href="{{ route('page.show', ['slug' => $footerLegal['privacy']]) }}">Privacy Policy</a></li>@endif
            </ul>
        </div>
    </div>
</footer>
@stack('scripts')
@if (!file_exists(public_path('build/manifest.json')))
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="{{ asset('js/kelika.js') }}" defer></script>
@endif
</body>
</html>

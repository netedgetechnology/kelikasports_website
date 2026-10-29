<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CMS') · KELIKA Admin</title>

    @include('layouts.partials.admin-fonts')

    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
              rel="stylesheet"
              integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
              crossorigin="anonymous">
        <link href="{{ asset('css/kelika.css') }}" rel="stylesheet">
    @endif
</head>
<body class="admin-shell">
<div class="container-fluid">
    <div class="row g-0">
        @php($adminBranding = \App\Support\AdminBranding::current())
        @php($navLinks = [
            ['Overview', route('admin.dashboard'), request()->routeIs('admin.dashboard')],
            ['Pages & Sections', route('admin.pages.index'), request()->routeIs('admin.pages.*')],
            ['Media Library', route('admin.media.index'), request()->routeIs('admin.media.*')],
            ['Menus', route('admin.menus.index'), request()->routeIs('admin.menus.*')],
            ['Global Settings', route('admin.settings.edit'), request()->routeIs('admin.settings.*')],
            ['URL Redirects', route('admin.redirects.index'), request()->routeIs('admin.redirects.*')],
        ])
        <aside class="col-lg-2 admin-sidebar" style="{{ \App\Support\AdminBranding::sidebarStyle() }}" data-admin-sidebar>
            <a class="admin-brand" href="{{ route('admin.dashboard') }}">
                @if ($adminBranding['logo'])
                    <img class="admin-brand__logo" src="{{ $adminBranding['logo'] }}" alt="KELIKA CMS">
                @else
                    <span class="admin-brand__name">KELIKA</span>
                @endif
                <small class="admin-brand__caption">Content Studio</small>
            </a>

            <nav aria-label="CMS navigation">
                @foreach ($navLinks as [$label, $url, $active])
                    <a href="{{ $url }}" @class(['active' => $active]) @if ($active) aria-current="page" @endif>{{ $label }}</a>
                @endforeach

                <div class="admin-nav-heading">Content</div>

                @foreach (['sport' => 'Sports', 'program' => 'Programs', 'facility' => 'Facilities', 'event' => 'Events', 'coach' => 'Coaches', 'faq' => 'FAQs', 'gallery' => 'Galleries', 'testimonial' => 'Testimonials'] as $type => $label)
                    @php($active = request()->routeIs('admin.content.*') && request()->route('type') === $type)
                    <a href="{{ route('admin.content.index', $type) }}" @class(['active' => $active]) @if ($active) aria-current="page" @endif>{{ $label }}</a>
                @endforeach

                @php($active = request()->routeIs('admin.enquiries.*'))
                <a href="{{ route('admin.enquiries.index') }}" @class(['active' => $active]) @if ($active) aria-current="page" @endif>Enquiries</a>

                @if (auth()->user()->isAdministrator())
                    @php($active = request()->routeIs('admin.users.*'))
                    <a href="{{ route('admin.users.index') }}" @class(['active' => $active]) @if ($active) aria-current="page" @endif>CMS Users</a>
                @endif

                <a href="{{ route('home') }}" target="_blank" rel="noopener">View Website ↗</a>
            </nav>

            <form method="post" action="{{ route('logout') }}" class="admin-signout">
                @csrf
                <button type="submit" class="btn btn-sm admin-sidebar-button w-100">Sign out</button>
            </form>
        </aside>

        <main class="col-lg-10 admin-main">
            <div class="admin-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-1">
                <div>
                    <p class="eyebrow mb-0">KELIKA CMS</p>
                    <h1 class="h4 fw-bold mb-0">@yield('heading', 'Dashboard')</h1>
                </div>
                <div class="small text-secondary">
                    Signed in as {{ auth()->user()->name }}
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success" role="status">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Please check the following:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

@if (!file_exists(public_path('build/manifest.json')))
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
            crossorigin="anonymous"></script>
    <script src="{{ asset('js/kelika.js') }}" defer></script>
@endif
</body>
</html>

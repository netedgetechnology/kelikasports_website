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
        <aside class="col-lg-2 admin-sidebar">
            <div class="admin-brand">
                KELIKA
                <small class="d-block fw-normal" style="font-size:.65rem;letter-spacing:.14em">
                    CONTENT STUDIO
                </small>
            </div>

            <nav aria-label="CMS navigation">
                <a href="{{ route('admin.dashboard') }}">Overview</a>
                <a href="{{ route('admin.pages.index') }}">Pages &amp; Sections</a>
                <a href="{{ route('admin.media.index') }}">Media Library</a>
                <a href="{{ route('admin.menus.index') }}">Menus</a>
                <a href="{{ route('admin.settings.edit') }}">Global Settings</a>
                <a href="{{ route('admin.redirects.index') }}">URL Redirects</a>

                <div class="text-uppercase small opacity-75 px-3 mt-4 mb-2">Content</div>

                @foreach (['sport' => 'Sports', 'program' => 'Programs', 'facility' => 'Facilities', 'event' => 'Events', 'coach' => 'Coaches', 'faq' => 'FAQs', 'gallery' => 'Galleries', 'testimonial' => 'Testimonials'] as $type => $label)
                    <a href="{{ route('admin.content.index', $type) }}">{{ $label }}</a>
                @endforeach

                <a href="{{ route('admin.enquiries.index') }}">Enquiries</a>

                @if (auth()->user()->isAdministrator())
                    <a href="{{ route('admin.users.index') }}">CMS Users</a>
                @endif

                <a href="{{ route('home') }}" target="_blank" rel="noopener">View Website ↗</a>
            </nav>

            <form method="post" action="{{ route('logout') }}" class="mt-4 px-2">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-light w-100">Sign out</button>
            </form>
        </aside>

        <main class="col-lg-10 admin-main">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
                <div>
                    <p class="eyebrow mb-1">KELIKA CMS</p>
                    <h1 class="h3 fw-bold mb-0">@yield('heading', 'Dashboard')</h1>
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

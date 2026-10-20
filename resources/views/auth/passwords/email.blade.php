<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password · KELIKA</title>
    @include('layouts.partials.admin-fonts')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link href="{{ asset('css/kelika.css') }}" rel="stylesheet">
</head>
<body class="admin-shell d-flex align-items-center justify-content-center p-3">
    <div class="admin-panel" style="width:min(100%,440px);padding:2rem">
        <a class="navbar-brand text-decoration-none" href="{{ route('home') }}">KELIKA</a>

        <h1 class="h4 fw-bold mt-4">Reset your password</h1>
        <p class="text-secondary">
            Enter your CMS account email. A reset link will be sent if the account is eligible.
        </p>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('password.email') }}">
            @csrf
            <label class="form-label" for="email">Email address</label>
            <input class="form-control mb-3"
                   type="email"
                   id="email"
                   name="email"
                   value="{{ old('email') }}"
                   required
                   autocomplete="email">
            <button class="btn btn-kelika w-100">Send Reset Link</button>
        </form>

        <a class="d-block mt-3" href="{{ route('login') }}">Back to login</a>
    </div>
</body>
</html>

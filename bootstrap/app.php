<?php

use App\Http\Middleware\EnsureAdminOrEditor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StartSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['cms.access' => EnsureAdminOrEditor::class]);
        $middleware->append(SecurityHeaders::class);
        // Same session handling, but 404s for visitors without a session do not create one.
        $middleware->web(replace: [\Illuminate\Session\Middleware\StartSession::class => StartSession::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Use Laravel's default exception handling. Production APP_DEBUG must remain false.
    })->create();

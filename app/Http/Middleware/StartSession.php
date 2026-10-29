<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession as BaseStartSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's session middleware, except that a "not found" response to a visitor who has no session yet leaves
 * nothing behind: no session row and no session cookie. The branded 404 page needs no session state, and without
 * this every scanner or mistyped URL would create a database session. Everything else is unchanged.
 */
class StartSession extends BaseStartSession
{
    private bool $discardSession = false;

    protected function handleStatefulRequest(Request $request, $session, Closure $next)
    {
        $hadSession = $request->cookies->has($session->getName());
        $this->discardSession = false;

        return parent::handleStatefulRequest($request, $session, function ($request) use ($next, $hadSession) {
            $response = $next($request);
            $this->discardSession = ! $hadSession && $response->getStatusCode() === 404;

            return $response;
        });
    }

    protected function addCookieToResponse(Response $response, Session $session)
    {
        if (! $this->discardSession) parent::addCookieToResponse($response, $session);
    }

    protected function saveSession($request)
    {
        if (! $this->discardSession) parent::saveSession($request);
    }
}

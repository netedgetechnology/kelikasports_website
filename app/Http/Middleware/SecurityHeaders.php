<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser protections on every Laravel response: no MIME sniffing, no framing by other sites (which would
 * let them overlay CMS buttons), and no full URLs leaked to other sites in the Referer header. A header a response
 * already sets is left alone. HSTS belongs to the HTTPS setup and a Content-Security-Policy to a separate review.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) $response->headers->set($name, $value);
        }

        return $response;
    }
}

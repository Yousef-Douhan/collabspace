<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hardening headers on every response (web pages, API, downloads).
 *
 * - nosniff: the browser never guesses a file's type, so an upload can't run as script.
 * - frame-ancestors / X-Frame-Options: no one can embed the app in an iframe (clickjacking).
 * - Referrer-Policy: other sites get our origin only, never full URLs with ids.
 * - Permissions-Policy: the app never needs camera, microphone, location or payments.
 * - HSTS: once seen over https, the browser refuses plain http for a year (production only).
 */
class SecurityHeaders
{
    /** @var array<string, string> */
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Content-Security-Policy' => "frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://github.com https://accounts.google.com; object-src 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            $response->headers->set($name, $value, false);
        }

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }
}

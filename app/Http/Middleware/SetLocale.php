<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the UI language: Accept-Language (API) → session choice → the user's saved locale → app default.
 */
class SetLocale
{
    public const SUPPORTED = ['en', 'ar', 'fr'];

    public function handle(Request $request, Closure $next): Response
    {
        $apiHeader = $request->is('api/*') && $request->headers->has('Accept-Language')
            ? $request->getPreferredLanguage(self::SUPPORTED)
            : null;

        $locale = collect([
            $apiHeader,
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->user()?->locale,
        ])->first(fn ($l) => in_array($l, self::SUPPORTED, true)) ?? config('app.locale');

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}

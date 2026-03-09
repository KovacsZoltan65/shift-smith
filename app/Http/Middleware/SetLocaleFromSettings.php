<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Facades\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A locale értékét a hierarchikus settings láncból állítja be minden kérés elején.
 */
final class SetLocaleFromSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = config('app.supported_locales', ['en', 'hu']);
        $fallbackLocale = config('app.fallback_locale', 'en');
        $resolvedLocale = Settings::getString('app.locale', config('app.locale', $fallbackLocale));
        $locale = \in_array($resolvedLocale, $supportedLocales, true)
            ? $resolvedLocale
            : $fallbackLocale;

        app()->setLocale($locale);

        return $next($request);
    }
}

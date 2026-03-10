<?php

namespace App\Http\Middleware;

use App\Services\CurrentCompany;
use App\Services\LocaleSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromSession
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly LocaleSettingsService $localeSettings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->localeSettings->resolve(
            companyId: $this->currentCompany->currentCompanyId($request),
            userId: $request->user() !== null ? (int) $request->user()->id : null,
        );

        app()->setLocale($locale);
        $request->setLocale($locale);

        return $next($request);
    }
}

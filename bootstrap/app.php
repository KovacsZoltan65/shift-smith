<?php

require_once __DIR__.'/../app/Support/helpers.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        //$middleware->web(prepend: [
        //    \App\Http\Middleware\InitializeTenantGroup::class,
        //]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\TrackMenuUsage::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\InitializeTenantGroup::class, // ide a végére vagy legalább session után
        ]);

        $middleware->alias([
            'ensure.company' => \App\Http\Middleware\EnsureCompanySelected::class,
            'ensure.tenant' => \App\Http\Middleware\EnsureTenantContext::class,
            'hq.landlord' => \App\Http\Middleware\EnsureHqLandlordContext::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperadmin::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            //
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

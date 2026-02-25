<?php

namespace App\Http\Middleware;

use App\Services\Menu\MenuContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackMenuUsage
{
    public function __construct(
        private readonly MenuContextService $menuContextService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // csak bejelentkezve
        $user = $request->user();
        if (!$user) {
            return $response;
        }

        // csak GET oldalak (menüpont megnyitás)
        if (!$request->isMethod('GET')) {
            return $response;
        }

        // csak Inertia navigációk (ne API/asset/egyéb)
        if (!$request->header('X-Inertia')) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        if (!$routeName) {
            return $response;
        }

        // Menü definíció: dashboard + minden *.index oldal
        $isMenuRoute = ($routeName === 'dashboard') || str_ends_with($routeName, '.index');
        if (!$isMenuRoute) {
            return $response;
        }

        // opcionális: ha hibás válasz, ne trackeljük
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $this->menuContextService->trackMenuUsage((int) $user->id, (string) $routeName);

        return $response;
    }
}

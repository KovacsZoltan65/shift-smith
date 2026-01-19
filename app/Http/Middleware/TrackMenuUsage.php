<?php

namespace App\Http\Middleware;

use App\Models\UserMenuStat;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackMenuUsage
{
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

        // biztos increment: insert esetén is működik
        $stat = UserMenuStat::query()->firstOrCreate(
            ['user_id' => $user->id, 'menu_key' => $routeName],
            ['hit_count' => 0, 'last_used_at' => now()]
        );

        $stat->increment('hit_count');
        $stat->forceFill(['last_used_at' => now()])->save();

        // menu_order cache invalidálás (userenként)
        Cache::forget("menu_order:user:{$user->id}");

        return $response;
    }
}

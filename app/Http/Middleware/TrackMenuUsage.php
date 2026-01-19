<?php

namespace App\Http\Middleware;

use App\Models\UserMenuStat;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackMenuUsage
{
    private array $allowedMenuKeys = [
        // Admin
        'users.index',
        'companies.index',

        // Security
        'permissions.index',
        'roles.index',

        // HR
        'employees.index',
        'assignments.index',
        'shifts.index',
        'planning.index',

        // Settings
        'settings.app',
        'settings.company',
        'settings.user',

        // plusz: dashboard is lehet
        'dashboard',
    ];
    
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // csak bejelentkezve
        $user = $request->user();
        if (!$user) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        if (!$routeName) {
            return $response;
        }

        // csak “menü” route-okat trackeljünk
        if (!in_array($routeName, $this->allowedMenuKeys, true)) {
            return $response;
        }

        // (opcionális) csak Inertia page navigációt trackeljünk:
        // if (!$request->header('X-Inertia')) return $response;

        UserMenuStat::query()->updateOrCreate(
            ['user_id' => $user->id, 'menu_key' => $routeName],
            [
                'hit_count' => \DB::raw('hit_count + 1'),
                'last_used_at' => now(),
            ]
        );

        return $response;
    }
}

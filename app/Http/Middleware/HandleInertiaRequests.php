<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        ds(
            $user
                ? $request->user()->getAllPermissions()->pluck('name')->values()
                : [],
            $user ? $user->getRoleNames()->values() : []
        );
        
        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info'    => fn () => $request->session()->get('info'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id'    => (int) $user->id,
                    'name'  => (string) $user->name,
                    'email' => (string) $user->email,
                ] : null,
                'can'  => $request->user() ? $request->user()->getPermissionArray() : [],
                'roles' => $user ? $user->getRoleNames()->values() : [],
                'permissions' => $user
                    ? $request->user()->getAllPermissions()->pluck('name')->values()
                    : [],
            ],
            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
        ];
    }
}

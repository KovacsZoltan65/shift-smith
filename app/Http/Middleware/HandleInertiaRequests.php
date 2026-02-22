<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly CompanyContextService $companyContext
    ) {}

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
        $menuOrder = [];
        $user = $request->user();
        $currentCompany = null;
        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        $selectableCompanyCount = 0;
        
        if ($user) {
            
            $needCache = config('cache.enable_menu', false);
            $cacheKey  = "menu_order:user:{$user->id}";
            $ttl       = (int) config('cache.menu_refresh_second', 60);
            
            $callback = function() use($user) {
                return DB::table('user_menu_stats')
                    ->where('user_id', $user->id)
                    ->orderByDesc('hit_count')
                    ->orderByDesc('last_used_at')
                    ->pluck('menu_key')
                    ->values()
                    ->all();
            };
            
            $menuOrder = $needCache
                ? Cache::remember($cacheKey, $ttl, $callback)
                : $callback();

            if ($user instanceof User && $currentCompanyId !== null) {
                $currentCompany = $this->companyContext->findSelectableCompany($user, $currentCompanyId);
            }

            if ($user instanceof User) {
                $selectableCompanyCount = $this->companyContext->countSelectableCompanies($user);
            }
        }

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
                'roles' => $user ? $user->getRoleNames()->values()->all() : [],
                'permissions' => $user
                    ? $request->user()->getAllPermissions()->pluck('name')->values()
                    : [],
            ],
            'companyContext' => [
                'current_company_id' => $currentCompanyId,
                'current_company' => $currentCompany ? [
                    'id' => (int) $currentCompany->id,
                    'name' => (string) $currentCompany->name,
                ] : null,
                'selectable_company_count' => $selectableCompanyCount,
            ],
            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
            'menu_order' => $menuOrder,
        ];
    }
}

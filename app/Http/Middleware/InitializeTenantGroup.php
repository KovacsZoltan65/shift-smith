<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TenantGroup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantGroup
{
    public function handle(Request $request, Closure $next): Response
    {
        // ha nincs session, nincs mit csinálni
        if (! $request->hasSession()) {
            return $next($request);
        }

        $tenantFinderClass = config('multitenancy.tenant_finder');
        if (! is_string($tenantFinderClass) || ! class_exists($tenantFinderClass)) {
            return $next($request);
        }

        $tenantFinder = app($tenantFinderClass);
        if (! method_exists($tenantFinder, 'findForRequest')) {
            return $next($request);
        }

        $tenant = $tenantFinder->findForRequest($request);

        if ($tenant !== null) {
            $tenant->makeCurrent();
        } else {
            TenantGroup::forgetCurrent();
        }

        // DEBUG (ideiglenesen)
        //if (app()->environment('local')) {
        //    logger()->info('tenant.current', [
        //        'session_tenant_group_id' => $request->session()->get('current_tenant_group_id'),
        //        'current_tenant_group_id' => TenantGroup::current()?->id,
        //        'user_id' => auth()->id(), // itt még lehet null, ez OK
        //        'path' => $request->path(),
        //    ]);
        //}

        return $next($request);
    }
}

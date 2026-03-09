<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Olyan middleware, amely leállítja a tenant-scoped route-okat, ha nincs TenantGroup kontextus.
 */
final class EnsureTenantContext
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    /**
     * Feltölti a request attribútumokat, amelyeket a tenant-aware downstream kód elvár.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantManager->ensureTenantContext($request);

        return $next($request);
    }
}

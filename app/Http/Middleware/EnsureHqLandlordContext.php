<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TenantGroup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureHqLandlordContext
{
    public function handle(Request $request, Closure $next): Response
    {
        TenantGroup::forgetCurrent();

        return $next($request);
    }
}

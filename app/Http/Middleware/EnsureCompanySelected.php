<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCompanySelected
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly CompanyContextService $companyContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (!$user instanceof User) {
            return $next($request);
        }

        if ($this->isHqRoute($request)) {
            return $next($request);
        }

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);

        if ($currentCompanyId !== null) {
            if ($this->companyContext->userCanSelectCompany($user, $currentCompanyId)) {
                return $next($request);
            }

            $this->currentCompany->clearCurrentCompany($request);
        }

        $companyCount = $this->companyContext->countSelectableCompanies($user);

        if ($companyCount === 1) {
            $companyId = $this->companyContext->firstSelectableCompanyId($user);

            if ($companyId !== null) {
                $this->currentCompany->setCurrentCompanyId($request, $companyId);
                return $next($request);
            }
        }

        if ($companyCount > 1) {
            return redirect()->route('company.select');
        }

        if ($this->companyContext->isSuperadmin($user)) {
            return $next($request);
        }

        abort(403, 'No company assigned');
    }

    private function isHqRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName) && str_starts_with($routeName, 'hq.');
    }
}

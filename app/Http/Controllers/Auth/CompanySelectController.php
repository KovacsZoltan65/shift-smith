<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\CurrentTenantGroup;

final class CompanySelectController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly CurrentCompany $currentCompany,
        private readonly CurrentTenantGroup $currentTenantGroup,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user instanceof User, 401);

        $companies = $this->companyContext->selectableCompanies($user);
        $companyCount = count($companies);

        if ($companyCount === 0) {
            if ($this->companyContext->isSuperadmin($user)) {
                $this->currentCompany->clearCurrentCompany($request);
                $this->currentTenantGroup->clearCurrentTenantGroup($request);
                return redirect()->intended(route('dashboard', absolute: false));
            }

            abort(403, 'No company assigned');
        }

        if ($companyCount === 1) {
            $companyId = (int) $companies[0]['id'];

            $this->currentCompany->setCurrentCompanyId($request, $companyId);

            $tenantGroupId = $this->tenantGroupIdForCompany($companyId);
            if ($tenantGroupId !== null) {
                $this->currentTenantGroup->setCurrentTenantGroupId($request, $tenantGroupId);
            } else {
                $this->currentTenantGroup->clearCurrentTenantGroup($request);
            }

            return redirect()->intended(route('dashboard', absolute: false));
        }

        return Inertia::render('Auth/SelectCompany', [
            'companies' => $companies,
            'currentCompanyId' => $this->currentCompany->currentCompanyId($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $companyId = (int) $validated['company_id'];

        if (!$this->companyContext->userCanSelectCompany($user, $companyId)) {
            abort(403, 'The selected company is not assigned to the current user.');
        }

        $this->currentCompany->setCurrentCompanyId($request, $companyId);

        $tenantGroupId = $this->tenantGroupIdForCompany($companyId);
        if ($tenantGroupId !== null) {
            $this->currentTenantGroup->setCurrentTenantGroupId($request, $tenantGroupId);
        } else {
            $this->currentTenantGroup->clearCurrentTenantGroup($request);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function tenantGroupIdForCompany(int $companyId): ?int
    {
        $tenantGroupId = Company::query()
            ->whereKey($companyId)
            ->value('tenant_group_id');

        if (! is_numeric($tenantGroupId)) {
            return null;
        }

        $id = (int) $tenantGroupId;

        return $id > 0 ? $id : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        $companies = $this->companyContext->selectableCompaniesForSwitch($user);
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

            $this->applyCompanyContext($request, $user, $companyId);

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

        if (!$this->companyContext->userCanSelectCompanyForSwitch($user, $companyId)) {
            abort(403, 'The selected company is not assigned to the current user.');
        }

        $this->applyCompanyContext($request, $user, $companyId);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function applyCompanyContext(Request $request, User $user, int $companyId): void
    {
        $this->currentCompany->setCurrentCompanyId($request, $companyId);

        $tenantGroupId = $this->companyContext->tenantGroupIdForCompanyForSwitch($user, $companyId);
        if ($tenantGroupId === null) {
            Log::error('company.missing_tenant_group_id', [
                'company_id' => $companyId,
                'user_id' => (int) $user->id,
            ]);

            $this->currentTenantGroup->clearCurrentTenantGroup($request);
            abort(500, 'Company tenant group is missing');
        }

        $this->currentTenantGroup->setCurrentTenantGroupId($request, $tenantGroupId);
    }
}

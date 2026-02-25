<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class CurrentCompanyContext
{
    public function resolve(Request $request): int
    {
        $companyId = (int) $request->session()->get('current_company_id', 0);
        $tenantGroupId = (int) $request->session()->get('current_tenant_group_id', 0);

        if ($companyId <= 0) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Nincs kiválasztott cég kontextus (current_company_id).');
        }

        if ($tenantGroupId <= 0) {
            $this->resetDriftedContext($request, $companyId, null, 'missing_tenant_group');
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Hiányzó tenant kontextus (current_tenant_group_id).');
        }

        $isValid = Company::query()
            ->whereKey($companyId)
            ->where('active', true)
            ->where('tenant_group_id', $tenantGroupId)
            ->exists();

        if ($isValid) {
            return $companyId;
        }

        $this->resetDriftedContext($request, $companyId, $tenantGroupId, 'invalid_company_for_tenant');

        abort(Response::HTTP_CONFLICT, 'A kiválasztott cég nem egyezik a tenant kontextussal.');
    }

    private function resetDriftedContext(
        Request $request,
        int $companyId,
        ?int $validatedTenantGroupId,
        string $reason
    ): void {
        $request->session()->forget([
            'current_company_id',
            'current_tenant_group_id',
        ]);

        Log::warning('company_context.drift_reset', [
            'reason' => $reason,
            'company_id' => $companyId,
            'validated_tenant_group_id' => $validatedTenantGroupId,
        ]);
    }
}

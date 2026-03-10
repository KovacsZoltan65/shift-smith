<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Employee\EmployeeSupervisorAssignRequest;
use App\Models\Employee;
use App\Policies\EmployeePolicy;
use App\Services\Cache\CacheNamespaces;
use App\Services\CurrentCompany;
use App\Services\EmployeeSupervisorService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class EmployeeSupervisorController extends Controller
{
    public function __construct(
        private readonly EmployeeSupervisorService $employeeSupervisorService,
        private readonly CurrentCompany $currentCompany,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function assign(EmployeeSupervisorAssignRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize(EmployeePolicy::PERM_UPDATE, $employee);

        $companyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! \is_int($companyId) || $companyId <= 0, 403, __('common.errors.no_company_selected'));

        $created = $this->employeeSupervisorService->assignSupervisor(
            companyId: $companyId,
            employeeId: (int) $employee->id,
            supervisorEmployeeId: (int) $request->integer('supervisor_employee_id'),
            validFrom: (string) $request->input('valid_from'),
            actorUserId: $request->user()?->id !== null ? (int) $request->user()->id : null
        );

        $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();

        return response()->json([
            'message' => __('employees.messages.supervisor_assigned'),
            'data' => [
                'id' => (int) $created->id,
                'employee_id' => (int) $created->employee_id,
                'supervisor_employee_id' => (int) $created->supervisor_employee_id,
                'valid_from' => (string) $created->valid_from?->format('Y-m-d'),
                'valid_to' => $created->valid_to?->format('Y-m-d'),
            ],
            'cache' => [
                'tag' => CacheNamespaces::tenantOrgHierarchy($tenantGroupId, $companyId),
            ],
        ], Response::HTTP_CREATED);
    }
}

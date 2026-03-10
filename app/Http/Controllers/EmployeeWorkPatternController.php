<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\EmployeeWorkPattern\EmployeeWorkPatternData;
use App\Http\Requests\EmployeeWorkPattern\AssignRequest;
use App\Http\Requests\EmployeeWorkPattern\ListByEmployeeRequest;
use App\Http\Requests\EmployeeWorkPattern\UnassignRequest;
use App\Http\Requests\EmployeeWorkPattern\UpdateRequest;
use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Policies\EmployeeWorkPatternPolicy;
use App\Services\CurrentCompany;
use App\Services\EmployeeWorkPatternService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dolgozó-munkarend hozzárendelés controller osztály.
 *
 * HTTP kérések kezelése munkarend hozzárendelésekhez.
 */
class EmployeeWorkPatternController extends Controller
{
    /**
     * @param EmployeeWorkPatternService $service Hozzárendelés service
     */
    public function __construct(
        private readonly EmployeeWorkPatternService $service,
        private readonly CurrentCompany $currentCompany,
    ) {}

    /**
     * Dolgozó munkarend hozzárendeléseinek listázása.
     *
     * @param ListByEmployeeRequest $request Validált kérés
     * @param int $employee Dolgozó azonosító
     * @return JsonResponse Hozzárendelés lista
     */
    public function index(ListByEmployeeRequest $request, int $employee): JsonResponse
    {
        $this->authorize(EmployeeWorkPatternPolicy::PERM_VIEW, EmployeeWorkPattern::class);

        $companyId = $this->resolveCurrentCompanyId($request);
        $employeeModel = Employee::query()->findOrFail($employee);
        $items = $this->service->listByEmployee((int) $employeeModel->id, $companyId);

        return response()->json([
            'message' => __('employees.messages.fetch_success'),
            'data' => $items,
        ], Response::HTTP_OK);
    }

    /**
     * Új munkarend hozzárendelése dolgozóhoz.
     *
     * @param AssignRequest $request Validált kérés
     * @param int $employee Dolgozó azonosító
     * @return JsonResponse Létrehozott hozzárendelés
     */
    public function assign(AssignRequest $request, int $employee): JsonResponse
    {
        $this->authorize(EmployeeWorkPatternPolicy::PERM_ASSIGN, EmployeeWorkPattern::class);

        $companyId = $this->resolveCurrentCompanyId($request);
        $employeeModel = Employee::query()->findOrFail($employee);
        $payload = $request->validated();
        $payload['employee_id'] = (int) $employeeModel->id;
        $payload['company_id'] = $companyId;

        $created = $this->service->assign(EmployeeWorkPatternData::from($payload));

        return response()->json([
            'message' => __('employees.messages.work_pattern_assigned'),
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    /**
     * Meglévő hozzárendelés frissítése.
     *
     * @param UpdateRequest $request Validált kérés
     * @param int $employee Dolgozó azonosító
     * @param int $id Hozzárendelés azonosító
     * @return JsonResponse Frissített hozzárendelés
     */
    public function update(UpdateRequest $request, int $employee, int $id): JsonResponse
    {
        $this->authorize(EmployeeWorkPatternPolicy::PERM_ASSIGN, EmployeeWorkPattern::class);

        $companyId = $this->resolveCurrentCompanyId($request);
        $employeeModel = Employee::query()->findOrFail($employee);
        $payload = $request->validated();
        $payload['employee_id'] = (int) $employeeModel->id;
        $payload['company_id'] = $companyId;

        $updated = $this->service->updateAssignment(
            $id,
            (int) $employeeModel->id,
            $companyId,
            EmployeeWorkPatternData::from($payload)
        );

        return response()->json([
            'message' => __('employees.messages.work_pattern_updated'),
            'data' => $updated,
        ], Response::HTTP_OK);
    }

    /**
     * Hozzárendelés törlése.
     *
     * @param UnassignRequest $request Validált kérés
     * @param int $employee Dolgozó azonosító
     * @param int $id Hozzárendelés azonosító
     * @return JsonResponse Törlés eredménye
     */
    public function destroy(UnassignRequest $request, int $employee, int $id): JsonResponse
    {
        $this->authorize(EmployeeWorkPatternPolicy::PERM_UNASSIGN, EmployeeWorkPattern::class);

        $companyId = $this->resolveCurrentCompanyId($request);
        $employeeModel = Employee::query()->findOrFail($employee);
        $deleted = $this->service->unassign($id, (int) $employeeModel->id, $companyId);

        return response()->json([
            'message' => $deleted ? __('employees.messages.work_pattern_deleted') : __('employees.messages.work_pattern_delete_failed'),
            'deleted' => (bool) $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function resolveCurrentCompanyId(Request $request): int
    {
        $companyId = $this->currentCompany->currentCompanyId($request);
        abort_if($companyId === null, 403, __('common.errors.no_company_selected'));

        return $companyId;
    }
}

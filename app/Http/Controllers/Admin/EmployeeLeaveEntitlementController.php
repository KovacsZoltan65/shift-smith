<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\LeaveEntitlementShowRequest;
use App\Services\EmployeeService;
use App\Services\Leave\EmployeeLeaveEntitlementService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class EmployeeLeaveEntitlementController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employees,
        private readonly EmployeeLeaveEntitlementService $service,
    ) {
    }

    public function show(LeaveEntitlementShowRequest $request, int $id): JsonResponse
    {
        $employee = $this->employees->getEmployee($id);
        $this->authorize('view', $employee);

        try {
            $result = $this->service->showForEmployee(
                employeeId: $id,
                year: (int) ($request->validated('year') ?? now()->year),
            );
        } catch (DomainException) {
            abort(Response::HTTP_NOT_FOUND, 'Az éves szabadság jogosultság nem érhető el ebben a cég kontextusban.');
        }

        return response()->json([
            'message' => 'Éves szabadság jogosultság sikeresen lekérve.',
            'data' => $result->toArray(),
        ], Response::HTTP_OK);
    }
}

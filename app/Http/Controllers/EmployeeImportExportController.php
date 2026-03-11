<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Employee\EmployeeExportRequest;
use App\Http\Requests\Employee\EmployeeImportRequest;
use App\Http\Requests\Employee\EmployeeTemplateRequest;
use App\Models\Employee;
use App\Policies\EmployeePolicy;
use App\Services\CurrentCompany;
use App\Services\EmployeeTransfer\EmployeeExportService;
use App\Services\EmployeeTransfer\EmployeeImportService;
use App\Services\EmployeeTransfer\EmployeeTemplateService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EmployeeImportExportController extends Controller
{
    public function __construct(
        private readonly EmployeeExportService $employeeExportService,
        private readonly EmployeeTemplateService $employeeTemplateService,
        private readonly EmployeeImportService $employeeImportService,
        private readonly CurrentCompany $currentCompany,
    ) {}

    public function export(EmployeeExportRequest $request): StreamedResponse
    {
        $this->authorize(EmployeePolicy::PERM_VIEW_ANY, Employee::class);

        $companyId = $this->currentCompany->currentCompanyId($request);
        abort_if($companyId === null, 403, __('common.errors.no_company_selected'));

        return $this->employeeExportService->export($companyId, $request->requestedFormat());
    }

    public function template(EmployeeTemplateRequest $request): StreamedResponse
    {
        $this->authorize(EmployeePolicy::PERM_VIEW_ANY, Employee::class);

        $companyId = $this->currentCompany->currentCompanyId($request);
        abort_if($companyId === null, 403, __('common.errors.no_company_selected'));

        return $this->employeeTemplateService->download($companyId, $request->requestedFormat());
    }

    public function import(EmployeeImportRequest $request): JsonResponse
    {
        $this->authorize(EmployeePolicy::PERM_CREATE, Employee::class);

        $companyId = $this->currentCompany->currentCompanyId($request);
        abort_if($companyId === null, 403, __('common.errors.no_company_selected'));

        return response()->json([
            'message' => __('employees.import.messages.completed'),
            'data' => $this->employeeImportService->import(
                $companyId,
                $request->requestedFormat(),
                $request->uploadedFile(),
            ),
        ], Response::HTTP_OK);
    }
}

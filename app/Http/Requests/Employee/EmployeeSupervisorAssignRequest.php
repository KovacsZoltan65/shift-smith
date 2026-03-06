<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use App\Policies\EmployeePolicy;
use App\Services\CurrentCompany;
use App\Services\HierarchyIntegrityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class EmployeeSupervisorAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! ($this->user()?->can('org_hierarchy.update') ?? false)) {
            return false;
        }

        return $this->user()?->can(EmployeePolicy::PERM_UPDATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'supervisor_employee_id' => ['nullable', 'integer', 'exists:employees,id', 'different:employee_id'],
            'valid_from' => ['required', 'date_format:Y-m-d'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $routeEmployee = $this->route('employee');
        $routeEmployeeId = is_numeric($routeEmployee) ? (int) $routeEmployee : (is_object($routeEmployee) ? (int) ($routeEmployee->id ?? 0) : null);

        if (is_int($routeEmployeeId) && $routeEmployeeId > 0) {
            $this->merge(['employee_id' => $routeEmployeeId]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = app(CurrentCompany::class)->currentCompanyId($this);
            if (! is_int($companyId) || $companyId <= 0) {
                $validator->errors()->add('employee_id', 'Hiányzó cég kontextus.');
                return;
            }

            try {
                $rawSupervisor = $this->input('supervisor_employee_id');
                $supervisorEmployeeId = is_numeric($rawSupervisor) ? (int) $rawSupervisor : null;

                app(HierarchyIntegrityService::class)->validateNewSupervisorRelationOrFail(
                    companyId: $companyId,
                    employeeId: (int) $this->integer('employee_id'),
                    supervisorEmployeeId: $supervisorEmployeeId,
                    validFrom: CarbonImmutable::parse((string) $this->input('valid_from')),
                    enforceOverlap: false,
                );
            } catch (\Illuminate\Validation\ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
    }
}

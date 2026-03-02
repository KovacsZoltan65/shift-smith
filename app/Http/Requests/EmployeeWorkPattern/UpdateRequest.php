<?php

declare(strict_types=1);

namespace App\Http\Requests\EmployeeWorkPattern;

use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\TenantGroup;
use App\Models\WorkPattern;
use App\Policies\EmployeeWorkPatternPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(EmployeeWorkPatternPolicy::PERM_ASSIGN, EmployeeWorkPattern::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'work_pattern_id' => ['required', 'integer', 'exists:work_patterns,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $employeeId = (int) $this->route('employee');
            $assignmentId = (int) $this->route('id');
            $workPatternId = (int) $this->input('work_pattern_id');
            $dateFrom = (string) $this->input('date_from');
            $dateTo = $this->input('date_to');
            $employee = Employee::query()->find($employeeId);
            $workPattern = WorkPattern::query()->find($workPatternId);
            $assignment = EmployeeWorkPattern::query()->find($assignmentId);
            $tenantGroupId = TenantGroup::current()?->id;
            $companyId = (int) $this->session()->get('current_company_id', 0);

            if (!$employee || !$workPattern || !$assignment) {
                $validator->errors()->add('work_pattern_id', 'Érvénytelen dolgozó vagy munkarend.');
                return;
            }

            if ((int) $assignment->employee_id !== $employeeId) {
                $validator->errors()->add('employee', 'A hozzárendelés nem ehhez a dolgozóhoz tartozik.');
                return;
            }

            if ($companyId <= 0) {
                $validator->errors()->add('company_id', 'Nincs kiválasztott cég kontextus.');
                return;
            }

            $employeeInCompany = $employee->companies()
                ->where('companies.id', $companyId)
                ->where('companies.active', true)
                ->where('company_employee.active', true)
                ->when($tenantGroupId !== null, fn ($q) => $q->where('companies.tenant_group_id', (int) $tenantGroupId))
                ->exists();

            if (! $employeeInCompany) {
                $validator->errors()->add('employee', 'A dolgozó nem tartozik a kiválasztott céghez.');
                return;
            }

            if ((int) $workPattern->company_id !== $companyId) {
                $validator->errors()->add('work_pattern_id', 'A munkarend nem a kiválasztott céghez tartozik.');
                return;
            }

            $hasOverlap = EmployeeWorkPattern::query()
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->where('id', '!=', $assignmentId)
                ->where(function ($q) use ($dateFrom): void {
                    $q->whereNull('date_to')
                        ->orWhereDate('date_to', '>=', $dateFrom);
                })
                ->where(function ($q) use ($dateTo): void {
                    if ($dateTo) {
                        $q->whereDate('date_from', '<=', (string) $dateTo);
                    }
                })
                ->exists();

            if ($hasOverlap) {
                $validator->errors()->add('date_from', 'A megadott időszak átfedésben van egy meglévő munkarenddel.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_to' => $this->filled('date_to') ? $this->input('date_to') : null,
        ]);
    }
}

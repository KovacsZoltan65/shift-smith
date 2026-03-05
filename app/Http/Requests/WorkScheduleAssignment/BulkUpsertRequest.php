<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkScheduleAssignment;

use App\Interfaces\WorkScheduleAssignmentRepositoryInterface;
use App\Models\WorkShiftAssignment;
use App\Policies\WorkScheduleAssignmentPolicy;
use App\Support\CurrentCompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(WorkScheduleAssignmentPolicy::PERM_CREATE, WorkShiftAssignment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'work_schedule_id' => ['required', 'integer', 'exists:work_schedules,id'],
            'work_shift_id' => ['required', 'integer', 'exists:work_shifts,id'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'dates' => ['required', 'array', 'min:1'],
            'dates.*' => ['date', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $companyId = app(CurrentCompanyContext::class)->resolve($this);
            /** @var WorkScheduleAssignmentRepositoryInterface $repository */
            $repository = app(WorkScheduleAssignmentRepositoryInterface::class);

            $employeeIds = array_values(array_unique(array_map('intval', $this->input('employee_ids', []))));
            if ($employeeIds !== [] && ! $repository->employeesBelongToCompany($companyId, $employeeIds)) {
                $validator->errors()->add('employee_ids', 'A kiválasztott dolgozók között cégidegen elem található.');
            }

            $dates = $this->input('dates', []);
            if (!is_array($dates) || empty($dates)) {
                return;
            }

            $min = min($dates);
            $max = max($dates);

            if (!is_string($min) || !is_string($max) || $min > $max) {
                $validator->errors()->add('dates', 'A dátumok tartománya érvénytelen.');
            }
        });
    }
}

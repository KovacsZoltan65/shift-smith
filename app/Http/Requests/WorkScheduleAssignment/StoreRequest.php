<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkScheduleAssignment;

use App\Models\WorkShiftAssignment;
use App\Policies\WorkScheduleAssignmentPolicy;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(WorkScheduleAssignmentPolicy::PERM_CREATE, WorkShiftAssignment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'work_schedule_id' => ['required', 'integer', 'exists:work_schedules,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_shift_id' => ['required', 'integer', 'exists:work_shifts,id'],
            'date' => ['required', 'date'],
        ];
    }
}

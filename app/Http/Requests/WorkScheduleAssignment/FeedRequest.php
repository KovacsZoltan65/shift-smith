<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkScheduleAssignment;

use App\Models\WorkShiftAssignment;
use App\Policies\WorkScheduleAssignmentPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(WorkScheduleAssignmentPolicy::PERM_VIEW_ANY, WorkShiftAssignment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'integer', 'exists:work_schedules,id'],
            'view_type' => ['required', 'string', Rule::in(['week', 'month', 'day'])],
            'week_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:53'],
            'week_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'work_shift_ids' => ['nullable', 'array'],
            'work_shift_ids.*' => ['integer', 'exists:work_shifts,id'],
            'position_ids' => ['nullable', 'array'],
            'position_ids.*' => ['integer', 'exists:positions,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $viewType = (string) $this->input('view_type', 'week');
        $today = CarbonImmutable::today();

        $this->merge([
            'view_type' => $viewType !== '' ? $viewType : 'week',
            'week_count' => $this->input('week_count', 1),
            'week_number' => $this->input('week_number', (int) $today->isoWeek()),
            'week_year' => $this->input('week_year', (int) $today->format('Y')),
            'month' => $this->input('month'),
            'year' => $this->input('year'),
            'date' => $this->input('date'),
        ]);
    }
}

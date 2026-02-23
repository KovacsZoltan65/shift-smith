<?php

declare(strict_types=1);

namespace App\Http\Requests\Scheduling\AutoPlan;

use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(WorkSchedulePolicy::PERM_AUTOPLAN) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'string', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],

            'demand' => ['required', 'array'],
            'demand.weekday' => ['required', 'array', 'min:1'],
            'demand.weekday.*.shift_id' => ['required', 'integer', 'exists:work_shifts,id'],
            'demand.weekday.*.required_count' => ['required', 'integer', 'min:1', 'max:500'],
            'demand.weekend' => ['required', 'array', 'min:1'],
            'demand.weekend.*.shift_id' => ['required', 'integer', 'exists:work_shifts,id'],
            'demand.weekend.*.required_count' => ['required', 'integer', 'min:1', 'max:500'],

            'rules' => ['nullable', 'array'],
            'rules.min_rest_hours' => ['nullable', 'integer', 'min:0', 'max:48'],
            'rules.max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:31'],
            'rules.weekend_fairness' => ['nullable', Rule::in([true, false, 0, 1, '0', '1'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rules = $this->input('rules');
        if (!\is_array($rules)) {
            return;
        }

        if (\array_key_exists('weekend_fairness', $rules)) {
            $rules['weekend_fairness'] = filter_var($rules['weekend_fairness'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        $this->merge(['rules' => $rules]);
    }
}

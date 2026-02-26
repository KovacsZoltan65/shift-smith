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
            'demand.weekend' => ['nullable', 'array'],
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
        $demand = $this->input('demand');
        if (!\is_array($demand)) {
            $demand = [];
        }

        if (!\array_key_exists('weekend', $demand) || !\is_array($demand['weekend'])) {
            $demand['weekend'] = [];
        }

        $rules = $this->input('rules');
        if (!\is_array($rules)) {
            $this->merge(['demand' => $demand]);
            return;
        }

        if (\array_key_exists('weekend_fairness', $rules)) {
            $rules['weekend_fairness'] = filter_var($rules['weekend_fairness'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        $this->merge([
            'demand' => $demand,
            'rules' => $rules,
        ]);
    }
}

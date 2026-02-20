<?php

declare(strict_types=1);

namespace App\Http\Requests\EmployeeWorkPattern;

use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\WorkPattern;
use App\Policies\EmployeeWorkPatternPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignRequest extends FormRequest
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
            $workPatternId = (int) $this->input('work_pattern_id');
            $dateFrom = (string) $this->input('date_from');
            $dateTo = $this->input('date_to');
            $employee = Employee::query()->find($employeeId);
            $workPattern = WorkPattern::query()->find($workPatternId);

            if (!$employee) {
                $validator->errors()->add('employee', 'A dolgozó nem található.');
                return;
            }

            if (!$workPattern) {
                $validator->errors()->add('work_pattern_id', 'A munkarend nem található.');
                return;
            }

            if ((int) $employee->company_id !== (int) $workPattern->company_id) {
                $validator->errors()->add('work_pattern_id', 'A munkarend és a dolgozó cége nem egyezik.');
                return;
            }

            $hasOverlap = EmployeeWorkPattern::query()
                ->where('company_id', (int) $employee->company_id)
                ->where('employee_id', $employeeId)
                ->where(function ($q) use ($dateFrom, $dateTo): void {
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

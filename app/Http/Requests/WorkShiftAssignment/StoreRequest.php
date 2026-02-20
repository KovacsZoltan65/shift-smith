<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkShiftAssignment;

use App\Models\Employee;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use App\Policies\WorkShiftAssigmentPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Műszakhoz történő dolgozó-hozzárendelés kérés validálása.
 */
class StoreRequest extends FormRequest
{
    /**
     * Jogosultság ellenőrzés.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(WorkShiftAssigmentPolicy::PERM_CREATE, WorkShiftAssignment::class) ?? false;
    }

    /**
     * Validációs szabályok.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'day' => ['required', 'date'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * További validáció.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $workShiftId = (int) $this->route('work_shift');
            $employeeId = (int) $this->input('employee_id');

            $workShift = WorkShift::query()->find($workShiftId);
            $employee = Employee::query()->find($employeeId);

            if (!$workShift || !$employee) {
                $validator->errors()->add('employee_id', 'Érvénytelen műszak vagy dolgozó.');
                return;
            }

            if ((int) $workShift->company_id !== (int) $employee->company_id) {
                $validator->errors()->add('employee_id', 'A dolgozó és a műszak cége nem egyezik.');
            }
        });
    }

    /**
     * Validáció előtti normalizálás.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ]);
    }
}

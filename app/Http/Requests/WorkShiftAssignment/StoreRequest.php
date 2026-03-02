<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkShiftAssignment;

use App\Models\Employee;
use App\Models\WorkShiftAssignment;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
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
            'work_schedule_id' => ['nullable', 'integer', 'exists:work_schedules,id'],
            'date' => ['required', 'date'],
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
            $workScheduleId = (int) $this->input('work_schedule_id');
            $date = (string) $this->input('date');

            $workShift = WorkShift::query()->find($workShiftId);
            $employee = Employee::query()->find($employeeId);

            if (!$workShift || !$employee) {
                $validator->errors()->add('employee_id', 'Érvénytelen műszak vagy dolgozó.');
                return;
            }

            $employeeInShiftCompany = $employee->companies()
                ->where('companies.id', (int) $workShift->company_id)
                ->where('companies.active', true)
                ->where('company_employee.active', true)
                ->exists();

            if (! $employeeInShiftCompany) {
                $validator->errors()->add('employee_id', 'A dolgozó és a műszak cége nem egyezik.');
            }

            if ($workScheduleId > 0) {
                $workSchedule = WorkSchedule::query()->find($workScheduleId);
                if (!$workSchedule) {
                    $validator->errors()->add('work_schedule_id', 'Érvénytelen beosztás.');
                    return;
                }

                if ((int) $workSchedule->company_id !== (int) $workShift->company_id) {
                    $validator->errors()->add('work_schedule_id', 'A beosztás és a műszak cége nem egyezik.');
                }

                if ($date < (string) $workSchedule->date_from->format('Y-m-d') || $date > (string) $workSchedule->date_to->format('Y-m-d')) {
                    $validator->errors()->add('date', 'A dátum nem esik a kiválasztott beosztás intervallumába.');
                }
            }
        });
    }
}

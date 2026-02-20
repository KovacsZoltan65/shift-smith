<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkScheduleAssignment;

use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use App\Models\WorkShift;
use App\Policies\WorkScheduleAssignmentPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * WorkScheduleAssignment frissítési kérés.
 */
class UpdateRequest extends FormRequest
{
    /**
     * Jogosultság ellenőrzés.
     *
     * @return bool True, ha a felhasználó frissíthet kiosztást.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(WorkScheduleAssignmentPolicy::PERM_UPDATE, WorkScheduleAssignment::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $schedule = $this->resolveSchedule();
        $companyId = $schedule ? (int) $schedule->company_id : 0;
        $id = (int) $this->route('id');

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'work_shift_id' => [
                'required',
                'integer',
                Rule::exists('work_shifts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'day' => [
                'required',
                'date',
                Rule::unique('work_schedule_assignments', 'day')
                    ->ignore($id)
                    ->where(fn ($q) => $q
                        ->where('company_id', $companyId)
                        ->where('work_schedule_id', (int) ($schedule?->id ?? 0))
                        ->where('employee_id', (int) $this->input('employee_id'))
                    ),
            ],
            'start_time' => ['nullable', 'date_format:H:i:s', 'required_with:end_time'],
            'end_time' => ['nullable', 'date_format:H:i:s', 'required_with:start_time', 'after:start_time'],
            'meta' => ['nullable', 'array'],
        ];
    }

    /**
     * Egyedi domain validációk:
     * - nap a schedule tartományon belül legyen
     * - employee és shift company scope ellenőrzés
     *
     * @param Validator $validator Laravel validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $schedule = $this->resolveSchedule();
            if (!$schedule) {
                $validator->errors()->add('schedule', 'A megadott beosztás nem található.');
                return;
            }

            $day = (string) $this->input('day', '');
            if ($day === '') {
                return;
            }

            if ($day < (string) $schedule->date_from->format('Y-m-d') || $day > (string) $schedule->date_to->format('Y-m-d')) {
                $validator->errors()->add('day', 'A nap nincs a beosztás érvényességi tartományában.');
            }

            $employee = Employee::query()->find((int) $this->input('employee_id'));
            $shift = WorkShift::query()->find((int) $this->input('work_shift_id'));
            $companyId = (int) $schedule->company_id;

            if (!$employee || (int) $employee->company_id !== $companyId) {
                $validator->errors()->add('employee_id', 'A dolgozó nem az adott céghez tartozik.');
            }

            if (!$shift || (int) $shift->company_id !== $companyId) {
                $validator->errors()->add('work_shift_id', 'A műszak nem az adott céghez tartozik.');
            }
        });
    }

    /**
     * Frissítési payload előállítása repository részére.
     *
     * @return array{
     *   employee_id: int,
     *   work_shift_id: int,
     *   day: string,
     *   start_time?: string|null,
     *   end_time?: string|null,
     *   meta?: array<string,mixed>|null
     * }
     */
    public function assignmentPayload(): array
    {
        $data = $this->validated();

        return [
            'employee_id' => (int) $data['employee_id'],
            'work_shift_id' => (int) $data['work_shift_id'],
            'day' => (string) $data['day'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'meta' => $data['meta'] ?? null,
        ];
    }

    /**
     * Aktuális route schedule feloldása.
     *
     * @return WorkSchedule|null A route-ból feloldott beosztás.
     */
    private function resolveSchedule(): ?WorkSchedule
    {
        $scheduleId = (int) $this->route('schedule');
        if ($scheduleId <= 0) {
            return null;
        }

        return WorkSchedule::query()->find($scheduleId);
    }
}

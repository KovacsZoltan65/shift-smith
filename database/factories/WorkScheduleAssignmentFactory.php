<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use App\Models\WorkShift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkScheduleAssignment>
 */
class WorkScheduleAssignmentFactory extends Factory
{
    protected $model = WorkScheduleAssignment::class;

    /**
     * @return array<string,mixed>
     */
    public function definition(): array
    {
        $companyId = (int) (Company::query()->inRandomOrder()->value('id') ?? 1);

        return [
            'company_id' => $companyId,
            'work_schedule_id' => (int) (WorkSchedule::query()->where('company_id', $companyId)->inRandomOrder()->value('id') ?? 1),
            'employee_id' => (int) (Employee::query()->where('company_id', $companyId)->inRandomOrder()->value('id') ?? 1),
            'work_shift_id' => (int) (WorkShift::query()->where('company_id', $companyId)->inRandomOrder()->value('id') ?? 1),
            'day' => CarbonImmutable::today()->format('Y-m-d'),
            'start_time' => null,
            'end_time' => null,
            'meta' => null,
        ];
    }
}

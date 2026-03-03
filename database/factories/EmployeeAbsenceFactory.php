<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAbsence>
 */
class EmployeeAbsenceFactory extends Factory
{
    protected $model = EmployeeAbsence::class;

    public function definition(): array
    {
        $dateFrom = fake()->dateTimeBetween('now', '+10 days');
        $dateTo = (clone $dateFrom)->modify('+1 day');

        return [
            'company_id' => Company::factory(),
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'sick_leave_category_id' => null,
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
            'minutes_per_day' => 480,
            'total_minutes' => 960,
            'note' => fake()->sentence(),
            'status' => 'approved',
            'created_by' => User::factory(),
        ];
    }
}

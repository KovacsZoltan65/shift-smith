<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => fake()->unique()->lexify('leave_????'),
            'name' => fake()->words(2, true),
            'category' => fake()->randomElement(['leave', 'sick_leave', 'paid_absence', 'unpaid_absence']),
            'affects_leave_balance' => true,
            'requires_approval' => true,
            'active' => true,
        ];
    }
}

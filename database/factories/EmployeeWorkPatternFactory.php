<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\WorkPattern;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeWorkPattern>
 */
class EmployeeWorkPatternFactory extends Factory
{
    protected $model = EmployeeWorkPattern::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyId = Company::query()->inRandomOrder()->value('id') ?? Company::factory();

        return [
            'company_id' => $companyId,
            'employee_id' => Employee::query()->where('company_id', $companyId)->inRandomOrder()->value('id')
                ?? Employee::factory()->state(['company_id' => $companyId]),
            'work_pattern_id' => WorkPattern::query()->where('company_id', $companyId)->inRandomOrder()->value('id')
                ?? WorkPattern::factory()->state(['company_id' => $companyId]),
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => null,
        ];
    }
}

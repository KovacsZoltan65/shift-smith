<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    protected $model = EmployeeProfile::class;

    public function definition(): array
    {
        /** @var Employee $employee */
        $employee = Employee::query()->inRandomOrder()->first() ?? Employee::factory()->create();
        $companyId = (int) $employee->company_id;
        $childrenCount = fake()->numberBetween(0, 3);
        $disabledChildrenCount = $childrenCount === 0 ? 0 : fake()->numberBetween(0, min(1, $childrenCount));

        return [
            'company_id' => $companyId,
            'employee_id' => (int) $employee->id,
            'children_count' => $childrenCount,
            'disabled_children_count' => $disabledChildrenCount,
            'is_disabled' => fake()->boolean(15),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::query()->inRandomOrder()->value('id'),
            'first_name' => $this->faker->firstName,
            'last_name'  => $this->faker->lastName,
            'email'      => $this->faker->unique()->safeEmail,
            'address'    => $this->faker->address, // ✅ ÚJ
            'position'   => $this->faker->jobTitle,
            'phone'      => $this->faker->phoneNumber,
            'hired_at'   => $this->faker->date(),
        ];
    }
}
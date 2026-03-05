<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        $companyId = Company::query()->inRandomOrder()->value('id') ?? Company::factory()->create()->id;
        $positionId = Position::query()
            ->where('company_id', $companyId)
            ->inRandomOrder()
            ->value('id');

        if ($positionId === null) {
            $positionId = Position::factory()->create(['company_id' => $companyId])->id;
        }

        return [
            'company_id' => $companyId,
            'first_name' => $this->faker->firstName,
            'last_name'  => $this->faker->lastName,
            'email'      => $this->faker->unique()->safeEmail,
            'address'    => $this->faker->address,
            'position_id'=> $positionId,
            'org_level'  => Employee::ORG_LEVEL_STAFF,
            'phone'      => $this->faker->phoneNumber,
            'birth_date' => $this->faker->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'hired_at'   => $this->faker->date(),
        ];
    }
}

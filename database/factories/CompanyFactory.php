<?php

namespace Database\Factories;

use App\Models\TenantGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_group_id' => TenantGroup::factory(),
            'name'    => fake()->company(),
            'email'   => fake()->email(),
            'address' => fake()->address(),
            'phone'   => fake()->phoneNumber(),
        ];
    }
}

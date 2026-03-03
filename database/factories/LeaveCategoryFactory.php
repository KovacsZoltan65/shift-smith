<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\LeaveCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveCategory>
 */
class LeaveCategoryFactory extends Factory
{
    protected $model = LeaveCategory::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => fake()->unique()->lexify('????????'),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'active' => true,
            'order_index' => fake()->numberBetween(1, 50),
        ];
    }
}

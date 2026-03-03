<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\SickLeaveCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SickLeaveCategory>
 */
class SickLeaveCategoryFactory extends Factory
{
    protected $model = SickLeaveCategory::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => 'slc_'.fake()->unique()->lexify('??????'),
            'name' => fake()->unique()->words(2, true),
            'order_index' => fake()->numberBetween(1, 20),
            'active' => true,
        ];
    }
}

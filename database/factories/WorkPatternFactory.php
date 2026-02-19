<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\WorkPattern;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkPattern>
 */
class WorkPatternFactory extends Factory
{
    protected $model = WorkPattern::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::query()->inRandomOrder()->value('id') ?? Company::factory(),
            'name' => 'Pattern ' . $this->faker->unique()->words(2, true),
            'type' => $this->faker->randomElement(['fixed_weekly', 'rotating_shifts', 'custom']),
            'cycle_length_days' => $this->faker->optional()->numberBetween(1, 28),
            'weekly_minutes' => $this->faker->optional()->numberBetween(300, 3000),
            'active' => true,
            'meta' => null,
        ];
    }
}

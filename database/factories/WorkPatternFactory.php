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
            'daily_work_minutes' => $this->faker->randomElement([360, 420, 480, 720]),
            'break_minutes' => $this->faker->randomElement([20, 30, 45, 60]),
            'core_start_time' => null,
            'core_end_time' => null,
            'active' => true,
        ];
    }
}

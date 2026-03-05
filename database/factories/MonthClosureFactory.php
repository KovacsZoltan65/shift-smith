<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthClosure>
 */
class MonthClosureFactory extends Factory
{
    protected $model = MonthClosure::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'year' => (int) $this->faker->numberBetween(2025, 2030),
            'month' => (int) $this->faker->numberBetween(1, 12),
            'closed_at' => now(),
            'closed_by_user_id' => User::factory(),
            'note' => $this->faker->optional()->sentence(),
        ];
    }
}

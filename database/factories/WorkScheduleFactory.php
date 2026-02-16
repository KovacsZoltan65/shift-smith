<?php

namespace Database\Factories;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    public function definition(): array
    {
        $start = Carbon::today()->subDays($this->faker->numberBetween(0, 60));
        $end = (clone $start)->addDays($this->faker->numberBetween(7, 60));

        return [
            'company_id' => Company::query()->inRandomOrder()->value('id'),
            'name' => $this->faker->randomElement([
                'Heti beosztás',
                'Havi beosztás',
                'Ünnepi beosztás',
                'Szezonális beosztás',
            ])." #".$this->faker->numberBetween(1, 999),
            'date_from' => $start->format('Y-m-d'),
            'date_to' => $end->format('Y-m-d'),
            'status' => $this->faker->randomElement(['draft', 'published']),
            'notes' => $this->faker->optional(0.4)->sentence(10),
        ];
    }
}

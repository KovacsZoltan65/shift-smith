<?php

namespace Database\Factories;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class WorkShiftFactory extends Factory
{
    public function definition(): array
    {
        // Kezdési idő (04:00 – 12:00 között)
        $startHour = $this->faker->numberBetween(4, 12);
        $startMinute = $this->faker->randomElement([0, 15, 30, 45]);

        $start = Carbon::createFromTime($startHour, $startMinute);

        // Műszak hossza (4 – 12 óra)
        $durationHours = $this->faker->numberBetween(4, 12);
        $end = (clone $start)->addHours($durationHours);

        // Ha átnyúl éjfélen, az rendben van (time mező kezeli)
        $breakMinutes = $this->faker->optional()->numberBetween(0, 60);

        $totalMinutes = $durationHours * 60;
        $workMinutes = $breakMinutes
            ? max($totalMinutes - $breakMinutes, 0)
            : $totalMinutes;

        return [
            'company_id' => Company::query()->inRandomOrder()->value('id') ?? Company::factory(),

            'name' => $this->faker->randomElement([
                'Reggeli műszak',
                'Délutáni műszak',
                'Éjszakai műszak',
                'Hosszú műszak',
                'Rövid műszak',
            ]),

            'start_time' => $start->format('H:i:s'),
            'end_time' => $end->format('H:i:s'),

            'work_time_minutes' => $workMinutes,
            'is_flexible' => $this->faker->boolean(20),
            'break_minutes' => $breakMinutes,

            'active' => $this->faker->boolean(85),
        ];
    }
}

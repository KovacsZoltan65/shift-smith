<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends Factory<\App\Models\WorkShiftAssignment>
 */
class WorkShiftAssignmentFactory extends Factory
{
    public function definition(): array
    {
        $day = Carbon::instance($this->faker->dateTimeBetween('-3 months', '+3 months'))->toDateString();

        return [
            // FK-ket a seeder felülírja state()-tel:
            'company_id' => 1,
            'work_shift_id' => 1,
            'employee_id' => 1,

            // opcionális mezők – igazítsd a tábládhoz:
            'day' => $day,
            // 'start_time' => $this->faker->time('H:i'),
            // 'end_time' => $this->faker->time('H:i'),
            // 'meta' => [],
        ];
    }
}

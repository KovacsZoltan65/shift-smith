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
        // Start dátum (ésszerű tartományban)
        $start_date = Carbon::instance(
            $this->faker->dateTimeBetween('-15 years', '-5 months')
        );
        
        // Különbség: minimum 5 hónap, maximum 10 év
        $monthsToAdd = $this->faker->numberBetween(5, 120);
        
        // End dátum mindig nagyobb lesz
        $end_date = (clone $start_date)->addMonths($monthsToAdd);
        
        return [
            'company_id' => Company::query()->inRandomOrder()->value('id'),
            'name'       => $this->faker->realText(10),
            'start_date' => $start_date->toDateString(),
            'end_date'   => $end_date->toDateString(),
        ];
    }
}
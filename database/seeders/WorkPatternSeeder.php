<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\WorkPattern;
use Illuminate\Database\Seeder;

/**
 * Munkarend törzsadatok seedelése.
 *
 * Cégenként létrehoz néhány alap WorkPattern rekordot,
 * hogy az új "Munkarendek" menüpont azonnal használható legyen.
 */
class WorkPatternSeeder extends Seeder
{
    /**
     * Seeder futtatása.
     *
     * @return void
     */
    public function run(): void
    {
        if (!Company::exists()) {
            $this->command->warn('⚠️ Nincs Company rekord, WorkPattern seeding kihagyva.');
            return;
        }

        Company::query()->each(function (Company $company): void {
            $basePatterns = [
                [
                    'name' => 'Fix nappal',
                    'type' => 'fixed_weekly',
                    'cycle_length_days' => null,
                    'weekly_minutes' => 2400,
                    'active' => true,
                    'meta' => ['label' => 'H-P 8:00-16:00'],
                ],
                [
                    'name' => '2 műszakos rotáció',
                    'type' => 'rotating_shifts',
                    'cycle_length_days' => 14,
                    'weekly_minutes' => 2400,
                    'active' => true,
                    'meta' => ['rotation' => '2x2'],
                ],
                [
                    'name' => 'Rugalmas',
                    'type' => 'custom',
                    'cycle_length_days' => null,
                    'weekly_minutes' => 2100,
                    'active' => true,
                    'meta' => ['note' => 'Egyedi megállapodás'],
                ],
            ];

            foreach ($basePatterns as $pattern) {
                WorkPattern::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'name' => $pattern['name'],
                    ],
                    [
                        'type' => $pattern['type'],
                        'cycle_length_days' => $pattern['cycle_length_days'],
                        'weekly_minutes' => $pattern['weekly_minutes'],
                        'active' => $pattern['active'],
                        'meta' => $pattern['meta'],
                    ]
                );
            }
        });
    }
}

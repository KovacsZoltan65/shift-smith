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
                    'name' => '8 órás',
                    'daily_work_minutes' => 480,
                    'break_minutes' => 30,
                    'core_start_time' => null,
                    'core_end_time' => null,
                    'active' => true,
                ],
                [
                    'name' => '12 órás',
                    'daily_work_minutes' => 720,
                    'break_minutes' => 60,
                    'core_start_time' => null,
                    'core_end_time' => null,
                    'active' => true,
                ],
                [
                    'name' => 'Rugalmas',
                    'daily_work_minutes' => 480,
                    'break_minutes' => 30,
                    'core_start_time' => '10:00:00',
                    'core_end_time' => '15:00:00',
                    'active' => true,
                ],
            ];

            foreach ($basePatterns as $pattern) {
                WorkPattern::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'name' => $pattern['name'],
                    ],
                    [
                        'daily_work_minutes' => $pattern['daily_work_minutes'],
                        'break_minutes' => $pattern['break_minutes'],
                        'core_start_time' => $pattern['core_start_time'],
                        'core_end_time' => $pattern['core_end_time'],
                        'active' => $pattern['active'],
                    ]
                );
            }
        });
    }
}

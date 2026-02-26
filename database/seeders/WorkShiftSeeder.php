<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\Company;
use App\Models\WorkShift;

class WorkShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Company::exists()) {
            $this->command->warn('⚠️ Nincs Company rekord, WorkShift seeding kihagyva.');
            return;
        }

        $items = [
            [
                'name' => 'Délelőttös',
                'start_time' => '06:00:00',
                'end_time' => '14:30:00',
                'is_flexible' => false,
                'active' => true,
            ],
            [
                'name' => 'Délutános',
                'start_time' => '14:00:00',
                'end_time' => '22:30:00',
                'is_flexible' => false,
                'active' => true,
            ],
            [
                'name' => 'Nappalos',
                'start_time' => '08:00:00',
                'end_time' => '16:30:00',
                'is_flexible' => false,
                'active' => true,
            ],
            [
                'name' => 'Rugalmas',
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'is_flexible' => true,
                'active' => true,
            ],
        ];

        Company::query()->each(function (Company $company) use ($items): void {
            foreach ($items as $item) {
                WorkShift::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'name' => $item['name'],
                    ],
                    [
                        'start_time' => $item['start_time'],
                        'end_time' => $item['end_time'],
                        'is_flexible' => $item['is_flexible'],
                        'active' => $item['active'],
                    ]
                );
            }
        });
    }
}


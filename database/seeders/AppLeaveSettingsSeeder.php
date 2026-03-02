<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppLeaveSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $minutesPerDay = 480;

        $rows = [
            [
                'key' => 'leave.minutes_per_day',
                'value' => json_encode($minutesPerDay),
                'type' => 'int',
                'group' => 'leave',
                'label' => 'Minutes per workday',
                'description' => 'All leave amounts are calculated in minutes. Default: 8h = 480 minutes.',
            ],
            [
                'key' => 'leave.annual.base_minutes',
                'value' => json_encode(20 * $minutesPerDay),
                'type' => 'int',
                'group' => 'leave',
                'label' => 'Base annual leave (minutes/year)',
                'description' => 'Base entitlement expressed in minutes/year. Default: 20 days.',
            ],
            [
                'key' => 'leave.annual.age_bonus_table',
                'value' => json_encode([
                    ['age_from' => 25, 'extra_minutes_per_year' => 1 * $minutesPerDay],
                    ['age_from' => 28, 'extra_minutes_per_year' => 2 * $minutesPerDay],
                    ['age_from' => 31, 'extra_minutes_per_year' => 3 * $minutesPerDay],
                    ['age_from' => 33, 'extra_minutes_per_year' => 4 * $minutesPerDay],
                    ['age_from' => 35, 'extra_minutes_per_year' => 5 * $minutesPerDay],
                    ['age_from' => 37, 'extra_minutes_per_year' => 6 * $minutesPerDay],
                    ['age_from' => 39, 'extra_minutes_per_year' => 7 * $minutesPerDay],
                    ['age_from' => 41, 'extra_minutes_per_year' => 8 * $minutesPerDay],
                    ['age_from' => 43, 'extra_minutes_per_year' => 9 * $minutesPerDay],
                    ['age_from' => 45, 'extra_minutes_per_year' => 10 * $minutesPerDay],
                ]),
                'type' => 'json',
                'group' => 'leave',
                'label' => 'Életkor szerinti pótszabadság táblázat',
                'description' => 'Az életkor alapján járó pótszabadság éves mértéke percben, alsó korhatár szerinti JSON tömbként.',
            ],
            [
                'key' => 'leave.annual.child_bonus_table',
                'value' => json_encode([
                    'by_children_count' => [
                        1 => 2 * $minutesPerDay,
                        2 => 4 * $minutesPerDay,
                        3 => 7 * $minutesPerDay, // 3 or more
                    ],
                    'disabled_child_extra_per_child_minutes' => 2 * $minutesPerDay,
                ]),
                'type' => 'json',
                'group' => 'leave',
                'label' => 'Child-based bonus leave rules',
                'description' => 'Extra leave minutes/year by children count + disabled child extra.',
            ],
            [
                'key' => 'leave.paternity.total_minutes',
                'value' => json_encode(10 * $minutesPerDay),
                'type' => 'int',
                'group' => 'leave',
                'label' => 'Paternity leave total (minutes)',
                'description' => 'Total paternity leave expressed in minutes.',
            ],
            [
                'key' => 'leave.parental.total_minutes',
                'value' => json_encode(44 * $minutesPerDay),
                'type' => 'int',
                'group' => 'leave',
                'label' => 'Parental leave total (minutes)',
                'description' => 'Total parental leave expressed in minutes.',
            ],
            [
                'key' => 'leave.youth.extra_minutes',
                'value' => json_encode(5 * $minutesPerDay),
                'type' => 'int',
                'group' => 'leave',
                'label' => 'Youth extra leave (minutes/year)',
                'description' => 'Extra leave for young workers expressed in minutes/year.',
            ],
            [
                'key' => 'leave.disability.extra_minutes',
                'value' => json_encode(5 * $minutesPerDay),
                'type' => 'int',
                'group' => 'leave',
                'label' => 'Disability extra leave (minutes/year)',
                'description' => 'Extra leave for disability/changed work capacity expressed in minutes/year.',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'value' => $row['value'],
                    'type' => $row['type'],
                    'group' => $row['group'],
                    'label' => $row['label'],
                    'description' => $row['description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}

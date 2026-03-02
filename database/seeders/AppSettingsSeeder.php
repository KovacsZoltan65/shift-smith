<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'key' => 'autoplan.min_rest_hours',
                'value' => 11,
                'type' => 'int',
                'group' => 'autoplan',
                'label' => 'Minimum rest hours between shifts',
                'description' => 'Minimum required rest time in hours between two work shifts.',
            ],
            [
                'key' => 'autoplan.max_consecutive_days',
                'value' => 6,
                'type' => 'int',
                'group' => 'autoplan',
                'label' => 'Maximum consecutive working days',
                'description' => 'Maximum allowed number of consecutive working days.',
            ],
            [
                'key' => 'autoplan.weekend_fairness',
                'value' => true,
                'type' => 'bool',
                'group' => 'autoplan',
                'label' => 'Weekend fairness enabled',
                'description' => 'Distribute weekend shifts evenly between employees.',
            ],
            [
                'key' => 'settings.user_legacy_global_override_enabled',
                'value' => false,
                'type' => 'bool',
                'group' => 'settings',
                'label' => 'Legacy user global override enabled',
                'description' => 'Allows fallback to company-less user_settings records during resolver migration.',
            ],
        ];

        foreach ($rows as $row) {

            DB::table('app_settings')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'value' => json_encode(
                        $row['value'],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
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

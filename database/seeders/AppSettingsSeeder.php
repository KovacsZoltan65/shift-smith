<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['key' => 'autoplan.min_rest_hours', 'value' => 11],
            ['key' => 'autoplan.max_consecutive_days', 'value' => 6],
            ['key' => 'autoplan.weekend_fairness', 'value' => true],
        ];

        foreach ($rows as $row) {
                DB::table('app_settings')->updateOrInsert(
                    ['key' => $row['key']],
                [
                    'value' => json_encode($row['value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}

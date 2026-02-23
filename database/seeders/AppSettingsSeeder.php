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
            ['key' => 'autoplan.min_rest_hours', 'value' => '11'],
            ['key' => 'autoplan.max_consecutive_days', 'value' => '6'],
            ['key' => 'autoplan.weekend_fairness', 'value' => '1'],
        ];

        foreach ($rows as $row) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $row['key']],
                ['value' => $row['value']]
            );
        }
    }
}

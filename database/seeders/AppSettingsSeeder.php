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
                'label' => 'Minimális pihenőidő a műszakok között',
                'description' => 'Két műszak között minimálisan előírt pihenőidő órákban.',
            ],
            [
                'key' => 'autoplan.max_consecutive_days',
                'value' => 6,
                'type' => 'int',
                'group' => 'autoplan',
                'label' => 'Maximális egymást követő munkanapok',
                'description' => 'Az egymást követő munkanapok maximálisan megengedett száma.',
            ],
            [
                'key' => 'autoplan.weekend_fairness',
                'value' => true,
                'type' => 'bool',
                'group' => 'autoplan',
                'label' => 'Hétvégi méltányosság engedélyezve',
                'description' => 'A hétvégi műszakokat egyenletesen ossza el a munkavállalók között.',
            ],
            [
                'key' => 'settings.user_legacy_global_override_enabled',
                'value' => false,
                'type' => 'bool',
                'group' => 'settings',
                'label' => 'Régi felhasználók globális felülírása engedélyezve',
                'description' => 'Lehetővé teszi a cég nélküli user_settings rekordok használatát a feloldó migrálása során.',
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

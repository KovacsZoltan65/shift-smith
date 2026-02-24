<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SettingsMeta;
use Illuminate\Database\Seeder;

class SettingsMetaSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'key' => 'autoplan.min_rest_hours',
                'group' => 'scheduling.autoplan',
                'label' => 'Minimum pihenőidő (óra)',
                'type' => 'int',
                'default_value' => 11,
                'description' => 'Két műszak között ennyi óra pihenőt kell biztosítani.',
                'options' => null,
                'validation' => ['required', 'integer', 'min:0', 'max:24'],
                'order_index' => 10,
                'is_editable' => true,
                'is_visible' => true,
            ],
            [
                'key' => 'autoplan.max_consecutive_days',
                'group' => 'scheduling.autoplan',
                'label' => 'Max. egymást követő nap',
                'type' => 'int',
                'default_value' => 6,
                'description' => 'Maximum ennyi egymás utáni napra osztható be ugyanaz a dolgozó.',
                'options' => null,
                'validation' => ['required', 'integer', 'min:1', 'max:31'],
                'order_index' => 20,
                'is_editable' => true,
                'is_visible' => true,
            ],
            [
                'key' => 'autoplan.weekend_fairness',
                'group' => 'scheduling.autoplan',
                'label' => 'Hétvégi fairness',
                'type' => 'bool',
                'default_value' => true,
                'description' => 'Hétvégi beosztásoknál külön kiegyensúlyozást alkalmaz.',
                'options' => null,
                'validation' => ['required', 'boolean'],
                'order_index' => 30,
                'is_editable' => true,
                'is_visible' => true,
            ],
        ];

        foreach ($rows as $row) {
            SettingsMeta::query()->updateOrCreate(
                ['key' => $row['key']],
                $row
            );
        }
    }
}


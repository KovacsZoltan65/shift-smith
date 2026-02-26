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
            [
                'key' => 'planning.allowed_weekdays',
                'group' => 'scheduling.autoplan',
                'label' => 'Tervezhető napok',
                'type' => 'multiselect',
                'default_value' => [1, 2, 3, 4, 5, 6, 7],
                'description' => 'ISO hét napok: 1=H, ... 7=V. Az AutoPlan csak ezeken a napokon generál.',
                'options' => [
                    ['label' => 'Hétfő', 'value' => 1],
                    ['label' => 'Kedd', 'value' => 2],
                    ['label' => 'Szerda', 'value' => 3],
                    ['label' => 'Csütörtök', 'value' => 4],
                    ['label' => 'Péntek', 'value' => 5],
                    ['label' => 'Szombat', 'value' => 6],
                    ['label' => 'Vasárnap', 'value' => 7],
                ],
                'validation' => ['required', 'array', 'min:1'],
                'order_index' => 40,
                'is_editable' => true,
                'is_visible' => true,
            ],
            [
                'key' => 'autoplan.weekend_policy',
                'group' => 'scheduling.autoplan',
                'label' => 'Hétvége policy',
                'type' => 'select',
                'default_value' => 'require_if_demand',
                'description' => 'skip: hétvége teljes kihagyása, allow: hétvége opcionális, require_if_demand: csak explicit hétvégi demand esetén tervez.',
                'options' => [
                    ['label' => 'Kihagyás', 'value' => 'skip'],
                    ['label' => 'Engedélyezett', 'value' => 'allow'],
                    ['label' => 'Csak demand esetén', 'value' => 'require_if_demand'],
                ],
                'validation' => ['required', 'string', 'in:skip,allow,require_if_demand'],
                'order_index' => 50,
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

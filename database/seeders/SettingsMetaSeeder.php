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
                'key' => 'app.locale',
                'group' => 'localization',
                'label' => 'Alkalmazás nyelve',
                'type' => 'select',
                'default_value' => config('app.locale', 'hu'),
                'description' => 'Az alkalmazás alapértelmezett nyelve. Company és User szinten felülírható.',
                'options' => config('app.available_locales', []),
                'validation' => ['required', 'in:'.implode(',', config('app.supported_locales', ['hu', 'en']))],
                'order_index' => 1,
                'is_editable' => true,
                'is_visible' => true,
            ],
            [
                'key' => 'ui.hierarchy.view_mode',
                'group' => 'ui.hierarchy',
                'label' => 'Hierarchia nézet mód',
                'type' => 'select',
                'default_value' => 'explorer',
                'description' => 'Explorer vagy network megjelenítési mód.',
                'options' => [
                    ['label' => 'Explorer', 'value' => 'explorer'],
                    ['label' => 'Network', 'value' => 'network'],
                ],
                'validation' => ['required', 'in:explorer,network'],
                'order_index' => 1,
                'is_editable' => true,
                'is_visible' => true,
            ],
            [
                'key' => 'ui.hierarchy.density',
                'group' => 'ui.hierarchy',
                'label' => 'Hierarchia sűrűség',
                'type' => 'select',
                'default_value' => 'comfortable',
                'description' => 'Compact vagy comfortable node méretezés.',
                'options' => [
                    ['label' => 'Compact', 'value' => 'compact'],
                    ['label' => 'Comfortable', 'value' => 'comfortable'],
                ],
                'validation' => ['required', 'in:compact,comfortable'],
                'order_index' => 2,
                'is_editable' => true,
                'is_visible' => true,
            ],
            [
                'key' => 'ui.hierarchy.show_position',
                'group' => 'ui.hierarchy',
                'label' => 'Pozíció megjelenítése hierarchiában',
                'type' => 'bool',
                'default_value' => true,
                'description' => 'Node címkékben jelenjen-e meg a pozíció.',
                'options' => null,
                'validation' => ['required', 'boolean'],
                'order_index' => 3,
                'is_editable' => true,
                'is_visible' => true,
            ],
            [
                'key' => 'org.hierarchy.recursive_supervisor_access',
                'group' => 'org.hierarchy',
                'label' => 'Rekurzív supervisor hozzáférés',
                'type' => 'bool',
                'default_value' => false,
                'description' => 'Ha igaz, a supervisor a teljes alhierarchiát kezelheti; ha hamis, csak a közvetlen beosztottakat.',
                'options' => null,
                'validation' => ['required', 'boolean'],
                'order_index' => 5,
                'is_editable' => true,
                'is_visible' => true,
            ],
            [
                'key' => 'autoplan.min_rest_minutes',
                'group' => 'scheduling.autoplan',
                'label' => 'Minimum pihenőidő két műszak között (perc)',
                'type' => 'int',
                'default_value' => 660,
                'description' => 'A két egymást követő munkavégzés között elvárt minimális pihenőidő percekben.',
                'options' => null,
                'validation' => ['required', 'integer', 'min:0', 'max:1440'],
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

        SettingsMeta::query()
            ->where('key', 'autoplan.min_rest_hours')
            ->delete();
    }
}

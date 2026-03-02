<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class LeaveCarryOverAppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $minutesPerDay = 480;

        $rows = [
            [
                'key' => 'leave.minutes_per_day',
                'value' => $minutesPerDay,
                'type' => 'int',
                'group' => 'leave',
                'label' => 'Munkaidő percekben egy napra',
                'description' => 'A rendszer minden szabadságmennyiséget percben számol. Alapértelmezett: 8 óra = 480 perc.',
            ],
            [
                'key' => 'leave.annual.base_minutes',
                'value' => 20 * $minutesPerDay,
                'type' => 'int',
                'group' => 'leave',
                'label' => 'Alapszabadság éves percekben',
                'description' => 'Az éves alapszabadság mértéke percben kifejezve. Alapértelmezett: 20 nap.',
            ],
            [
                'key' => 'leave.annual.age_bonus_table',
                'value' => [
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
                ],
                'type' => 'json',
                'group' => 'leave',
                'label' => 'Életkor szerinti pótszabadság táblázat',
                'description' => 'Az életkor alapján járó pótszabadság éves mértéke percben, alsó korhatár szerinti JSON tömbként tárolva.',
            ],
            [
                'key' => 'leave.annual.child_bonus_table',
                'value' => [
                    'by_children_count' => [
                        '1' => 2 * $minutesPerDay,
                        '2' => 4 * $minutesPerDay,
                        '3' => 7 * $minutesPerDay,
                    ],
                    'disabled_child_extra_per_child_minutes' => 2 * $minutesPerDay,
                ],
                'type' => 'json',
                'group' => 'leave',
                'label' => 'Gyermekek utáni pótszabadság táblázat',
                'description' => 'Gyermekek száma szerinti éves pótszabadság percekben, valamint a fogyatékos gyermek utáni többlet percben.',
            ],
            [
                'key' => 'leave.youth.extra_minutes',
                'value' => 5 * $minutesPerDay,
                'type' => 'int',
                'group' => 'leave',
                'label' => 'Fiatal munkavállalói pótszabadság percekben',
                'description' => 'A 18 év alatti munkavállalóknak járó éves pótszabadság percekben.',
            ],
            [
                'key' => 'leave.disability.extra_minutes',
                'value' => 5 * $minutesPerDay,
                'type' => 'int',
                'group' => 'leave',
                'label' => 'Fogyatékosság miatti pótszabadság percekben',
                'description' => 'A fogyatékossággal vagy megváltozott munkaképességgel élő munkavállalóknak járó éves pótszabadság percekben.',
            ],
            [
                'key' => 'leave.carryover.enabled',
                'value' => true,
                'type' => 'bool',
                'group' => 'leave',
                'label' => 'Szabadság átvitel engedélyezve',
                'description' => 'Meghatározza, hogy a szabadság átvitel szabályrendszere aktív-e.',
            ],
            [
                'key' => 'leave.carryover.october_entry_cutoff_month_day',
                'value' => '10-01',
                'type' => 'string',
                'group' => 'leave',
                'label' => 'Októberi belépési határnap (HH-NN)',
                'description' => 'Ha a munkaviszony ezen dátum után kezdődik, az arányos szabadság a következő év március 31-ig kiadható.',
            ],
            [
                'key' => 'leave.carryover.october_entry_valid_until_month_day',
                'value' => '03-31',
                'type' => 'string',
                'group' => 'leave',
                'label' => 'Októberi belépés érvényességi határnapja (HH-NN)',
                'description' => 'Az október 1. utáni belépők szabadságának kiadási határideje (MM-DD formátumban).',
            ],
            [
                'key' => 'leave.carryover.age_bonus_transfer_fraction',
                'value' => 0.25,
                'type' => 'number',
                'group' => 'leave',
                'label' => 'Életkor szerinti pótszabadság átvihető aránya',
                'description' => 'Az életkor alapján járó pótszabadság azon része (arány), amely megállapodás esetén átvihető a következő évre.',
            ],
            [
                'key' => 'leave.carryover.age_bonus_transfer_requires_agreement',
                'value' => true,
                'type' => 'bool',
                'group' => 'leave',
                'label' => 'Megállapodás szükséges az életkor pótszabadság átviteléhez',
                'description' => 'Meghatározza, hogy az életkor szerinti pótszabadság átvitele munkáltatói és munkavállalói megállapodáshoz kötött-e.',
            ],
            [
                'key' => 'leave.carryover.valid_until_month_day_for_agreement',
                'value' => '03-31',
                'type' => 'string',
                'group' => 'leave',
                'label' => 'Megállapodás szerinti átvitel érvényességi határnapja (HH-NN)',
                'description' => 'Az életkor szerinti pótszabadság megállapodás alapján történő átvitelének kiadási határideje.',
            ],
            [
                'key' => 'leave.carryover.blocked_reason_grace_days',
                'value' => 60,
                'type' => 'int',
                'group' => 'leave',
                'label' => 'Akadály miatti kiadási határidő (nap)',
                'description' => 'Ha a munkavállaló akadályoztatva volt (pl. betegség, szülési szabadság), a szabadságot az akadály megszűnésétől számított ennyi napon belül kell kiadni.',
            ],
            [
                'key' => 'leave.carryover.employer_exception_grace_days',
                'value' => 60,
                'type' => 'int',
                'group' => 'leave',
                'label' => 'Munkáltatói kivételes ok miatti kiadási határidő (nap)',
                'description' => 'Ha a szabadság kiadása kivételes gazdasági vagy működési okból maradt el, ennyi napon belül kell azt kiadni.',
            ],
            [
                'key' => 'leave.carryover.allow_legacy_global_user_override',
                'value' => false,
                'type' => 'bool',
                'group' => 'leave',
                'label' => 'Legacy globális felhasználói felülírás engedélyezése',
                'description' => 'Engedélyezi a company_id nélküli user_settings rekordok globális felülírásként való értelmezését (nem törvényi beállítás).',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'value' => json_encode($row['value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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

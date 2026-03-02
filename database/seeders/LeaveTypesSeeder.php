<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class LeaveTypesSeeder extends Seeder
{
    public function run(): void
    {
        $defaultTypes = [
            [
                'code' => 'annual_leave',
                'name' => 'Szabadság',
                'category' => 'leave',
                'affects_leave_balance' => true,
                'requires_approval' => true, 'active' => true,
            ],
            [
                'code' => 'sick_leave',
                'name' => 'Betegszabadság',
                'category' => 'sick_leave',
                'affects_leave_balance' => false,
                'requires_approval' => true,
                'active' => true,
            ],
            [
                'code' => 'unpaid_leave',
                'name' => 'Fizetés nélküli távollét',
                'category' => 'unpaid_absence',
                'affects_leave_balance' => false,
                'requires_approval' => true,
                'active' => true,
            ],
            [
                'code' => 'parental_leave',
                'name' => 'Szülői szabadság',
                'category' => 'paid_absence',
                'affects_leave_balance' => false,
                'requires_approval' => true,
                'active' => true,
            ],
            [
                'code' => 'paternity_leave',
                'name' => 'Apasági szabadság',
                'category' => 'paid_absence',
                'affects_leave_balance' => false,
                'requires_approval' => true,
                'active' => true,
            ],
            [
                'code' => 'maternity_leave',
                'name' => 'Anyasági szabadság',
                'category' => 'paid_absence',
                'affects_leave_balance' => false,
                'requires_approval' => true,
                'active' => true,
            ],
            [
                'code' => 'bereavement_leave',
                'name' => 'Temetési távollét',
                'category' => 'paid_absence',
                'affects_leave_balance' => false,
                'requires_approval' => true,
                'active' => true,
            ],
            [
                'code' => 'compensatory_leave',
                'name' => 'Túlóra kompenzáció (szabadidő)',
                'category' => 'paid_absence',
                'affects_leave_balance' => false,
                'requires_approval' => false,
                'active' => true,
            ],
        ];

        Company::query()->each(function (Company $company) use ($defaultTypes) {
            foreach ($defaultTypes as $type) {
                DB::table('leave_types')->updateOrInsert(
                    [
                        'company_id' => $company->id,
                        'code' => $type['code'],
                    ],
                    [
                        'name' => $type['name'],
                        'category' => $type['category'],
                        'affects_leave_balance' => $type['affects_leave_balance'],
                        'requires_approval' => $type['requires_approval'],
                        'active' => $type['active'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });
    }
}
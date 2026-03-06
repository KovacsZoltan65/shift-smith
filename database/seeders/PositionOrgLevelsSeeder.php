<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\TenantGroup;
use App\Services\Org\PositionOrgLevelService;
use Illuminate\Database\Seeder;

final class PositionOrgLevelsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['Rakodó', 'staff'],
            ['Rakodo', 'staff'],
            ['Dolgozó', 'staff'],
            ['Dolgozo', 'staff'],
            ['Csoportvezető', 'team_lead'],
            ['Csoportvezeto', 'team_lead'],
            ['Műszakvezető', 'shift_lead'],
            ['Muszakvezeto', 'shift_lead'],
            ['Műszak vezető', 'shift_lead'],
            ['Muszak vezeto', 'shift_lead'],
            ['Osztályvezető', 'department_head'],
            ['Osztalyvezeto', 'department_head'],
            ['Manager', 'manager'],
            ['Igazgató', 'ceo'],
            ['Igazgato', 'ceo'],
            ['CEO', 'ceo'],
        ];

        TenantGroup::query()->orderBy('id')->each(function (TenantGroup $tenant) use ($rows): void {
            $tenant->makeCurrent();
            try {
                Company::query()
                    ->where('tenant_group_id', (int) $tenant->id)
                    ->orderBy('id')
                    ->get(['id'])
                    ->each(function (Company $company) use ($rows): void {
                        foreach ($rows as [$label, $level]) {
                            app(PositionOrgLevelService::class)->upsertMapping(
                                companyId: (int) $company->id,
                                positionLabel: (string) $label,
                                orgLevel: (string) $level,
                                active: true
                            );
                        }
                    });
            } finally {
                TenantGroup::forgetCurrent();
            }
        });
    }
}


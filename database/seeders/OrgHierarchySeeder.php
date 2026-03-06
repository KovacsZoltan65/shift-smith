<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\TenantGroup;
use App\Services\Org\OrgHierarchyGenerator;
use Illuminate\Database\Seeder;

final class OrgHierarchySeeder extends Seeder
{
    public function run(): void
    {
        $seed = (int) env('ORG_SEED', 123);
        fake()->seed($seed);

        $totals = [
            'companies' => 0,
            'createdEmployees' => 0,
            'assignedRelations' => 0,
            'historyChanges' => 0,
        ];

        TenantGroup::query()
            ->orderBy('id')
            ->each(function (TenantGroup $tenant) use (&$totals, $seed): void {
                $tenant->makeCurrent();

                try {
                    $companies = Company::query()
                        ->where('tenant_group_id', (int) $tenant->id)
                        ->orderBy('id')
                        ->get();

                    foreach ($companies as $company) {
                        /** @var Company $company */
                        $stats = app(OrgHierarchyGenerator::class)->generateForCompany(
                            company: $company,
                            tenantGroupId: (int) $tenant->id,
                            seed: $seed
                        );

                        $totals['companies']++;
                        $totals['createdEmployees'] += (int) $stats['createdEmployees'];
                        $totals['assignedRelations'] += (int) $stats['assignedRelations'];
                        $totals['historyChanges'] += (int) $stats['historyChanges'];

                        if ($this->command !== null) {
                            $this->command->info(sprintf(
                                '[tenant=%d company=%d] createdEmployees=%d assignedRelations=%d historyChanges=%d',
                                (int) $tenant->id,
                                (int) $company->id,
                                (int) $stats['createdEmployees'],
                                (int) $stats['assignedRelations'],
                                (int) $stats['historyChanges']
                            ));
                        }
                    }
                } finally {
                    TenantGroup::forgetCurrent();
                }
            });

        if ($this->command !== null) {
            $this->command->newLine();

            $this->command->info(\sprintf(
            'OrgHierarchySeeder summary: companies=%03d createdEmployees=%03d assignedRelations=%03d historyChanges=%03d',
            $totals['companies'],
                $totals['createdEmployees'],
                $totals['assignedRelations'],
                $totals['historyChanges'],
        ));
        }
    }
}


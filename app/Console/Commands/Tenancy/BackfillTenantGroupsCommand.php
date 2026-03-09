<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenancy;

use App\Models\Company;
use App\Models\TenantGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillTenantGroupsCommand extends Command
{
    protected $signature = 'tenancy:backfill-tenant-groups {--chunk=200}';

    protected $description = 'Creates tenant groups for companies missing tenant_group_id and assigns them.';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $created = 0;
        $skipped = 0;

        Company::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($companies) use (&$created, &$skipped): void {
                foreach ($companies as $company) {
                    if ($company->tenant_group_id !== null) {
                        $skipped++;
                        continue;
                    }

                    DB::transaction(function () use ($company, &$created): void {
                        $company->refresh();

                        if ($company->tenant_group_id !== null) {
                            return;
                        }

                        $tenantGroup = TenantGroup::query()->create([
                            'name' => $company->name,
                            'code' => $this->makeUniqueCode($company),
                            'slug' => $this->makeUniqueSlug($company),
                            'active' => true,
                        ]);

                        $company->tenant_group_id = $tenantGroup->id;
                        $company->save();
                        $created++;
                    });
                }
            });

        $remainingNull = Company::query()->whereNull('tenant_group_id')->count();

        $this->info(sprintf('Tenant group backfill finished. created=%d, skipped=%d, remaining_null=%d', $created, $skipped, $remainingNull));

        if ($remainingNull > 0) {
            $this->error('Some companies still do not have tenant_group_id. Re-run the command.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function makeUniqueSlug(Company $company): string
    {
        $baseSlug = Str::slug($company->name);
        if ($baseSlug === '') {
            $baseSlug = 'company';
        }

        if (!TenantGroup::query()->where('slug', $baseSlug)->exists()) {
            return $baseSlug;
        }

        $slugWithId = $baseSlug.'-'.$company->id;
        if (!TenantGroup::query()->where('slug', $slugWithId)->exists()) {
            return $slugWithId;
        }

        $counter = 1;
        do {
            $candidate = $slugWithId.'-'.$counter;
            $counter++;
        } while (TenantGroup::query()->where('slug', $candidate)->exists());

        return $candidate;
    }

    private function makeUniqueCode(Company $company): string
    {
        $baseCode = Str::upper(Str::slug($company->name, '_'));
        if ($baseCode === '') {
            $baseCode = 'TENANT_GROUP';
        }

        $candidate = Str::limit($baseCode, 50, '');
        $counter = 1;

        while (TenantGroup::query()->withTrashed()->where('code', $candidate)->exists()) {
            $suffix = '_'.$counter;
            $candidate = Str::limit($baseCode, 50 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $candidate;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\User;

trait InteractsWithTenantSession
{
    /**
     * @param array<string,mixed> $tenantAttributes
     * @param array<string,mixed> $companyAttributes
     * @return array{0:TenantGroup,1:Company}
     */
    protected function createTenantWithCompany(array $tenantAttributes = [], array $companyAttributes = []): array
    {
        $tenantGroup = TenantGroup::factory()->create($tenantAttributes);
        $company = Company::factory()->create([
            ...$companyAttributes,
            'tenant_group_id' => (int) ($companyAttributes['tenant_group_id'] ?? $tenantGroup->id),
        ]);

        return [$tenantGroup, $company];
    }

    protected function actingAsUserInCompany(User $user, Company $company, bool $makeCurrent = true): static
    {
        $user->companies()->syncWithoutDetaching([(int) $company->id]);

        if ($makeCurrent) {
            TenantGroup::query()->whereKey((int) $company->tenant_group_id)->first()?->makeCurrent();
        }

        return $this
            ->actingAs($user)
            ->withSession([
                'current_company_id' => (int) $company->id,
                'current_tenant_group_id' => (int) $company->tenant_group_id,
            ]);
    }
}


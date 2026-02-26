<?php

declare(strict_types=1);

namespace App\Repositories\Dashboard;

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;

final class DashboardRepository implements DashboardRepositoryInterface
{
    private const NS_DASHBOARD_STATS = 'dashboard.stats';
    private const TAG_DASHBOARD = 'dashboard';

    public function __construct(
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {}

    /**
     * Summary of getStats
     * @param int $companyId
     * @return array|array{companies: int, employees: int, users: int, work_shifts: int}
     */
    public function getStats(int $companyId): array
    {
        [$tenantGroupId, $scopedCompanyId] = $this->resolveTenantScopedCompany($companyId);

        $queryCallback = function () use ($tenantGroupId, $scopedCompanyId): array {
            $users = (int) User::query()
                ->whereHas('companies', fn ($q) => $q
                    ->where('companies.id', $scopedCompanyId)
                    ->where('tenant_group_id', $tenantGroupId))
                ->distinct('users.id')
                ->count('users.id');

            $employees = (int) Employee::query()
                ->where('company_id', $scopedCompanyId)
                ->where('active', true)
                ->count();

            $companies = (int) Company::query()
                ->where('tenant_group_id', $tenantGroupId)
                ->where('active', true)
                ->count();

            $workShifts = (int) WorkShift::query()
                ->where('company_id', $scopedCompanyId)
                ->where('active', true)
                ->count();

            return [
                'users' => $users,
                'employees' => $employees,
                'companies' => $companies,
                'work_shifts' => $workShifts,
            ];
        };

        if (! (bool) config('cache.enable_dashboard', false)) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get(self::NS_DASHBOARD_STATS);
        $key = "tenant:{$tenantGroupId}:dashboard:stats:company:{$scopedCompanyId}:v{$version}";

        /** @var array{users:int,employees:int,companies:int,work_shifts:int} $stats */
        $stats = $this->cacheService->remember(
            tag: self::TAG_DASHBOARD,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 120),
        );

        return $stats;
    }

    public function getRecentUsers(int $companyId, int $limit = 5): array
    {
        [$tenantGroupId, $scopedCompanyId] = $this->resolveTenantScopedCompany($companyId);
        $limit = max(1, min($limit, 20));

        /** @var array<int, array{id:int,name:string,email:string,created_at:string}> $rows */
        $rows = User::query()
            ->whereHas('companies', fn ($q) => $q
                ->where('companies.id', $scopedCompanyId)
                ->where('tenant_group_id', $tenantGroupId))
            ->latest()
            ->limit($limit)
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(static fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'created_at' => (string) $user->created_at,
            ])
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @return array{0:int,1:int} [tenantGroupId, companyId]
     */
    private function resolveTenantScopedCompany(int $companyId): array
    {
        abort_if($companyId <= 0, 403, 'No company selected');

        $tenantGroupId = (int) (TenantGroup::current()?->id ?? 0);
        abort_if($tenantGroupId <= 0, 422, 'Missing tenant context');

        $company = Company::query()
            ->whereKey($companyId)
            ->where('tenant_group_id', $tenantGroupId)
            ->where('active', true)
            ->firstOrFail(['id']);

        return [$tenantGroupId, (int) $company->id];
    }
}


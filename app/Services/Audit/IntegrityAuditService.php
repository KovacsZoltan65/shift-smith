<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Data\Audit\AuditCheckResultData;
use App\Data\Audit\AuditReportData;
use App\Models\Admin\Permission;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\CompanyUser;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use App\Support\MenuPermissions;
use Illuminate\Database\Eloquent\Builder;

class IntegrityAuditService
{
    /**
     * @param array<int, int|string> $tenantIds
     */
    public function run(array $tenantIds, bool $fix, bool $verbose): AuditReportData
    {
        $resolvedTenantIds = $this->resolveTenantIds($tenantIds);
        $checks = [];

        $checks[] = $this->checkCompaniesMissingTenantGroupId();

        foreach ($resolvedTenantIds as $tenantId) {
            $checks[] = $this->checkTenantContextConsistency($tenantId);
            $checks[] = $this->checkCompanyEmployeeDuplicates($tenantId);
            $checks[] = $this->checkCompanyUserDuplicates($tenantId);
            $checks[] = $this->checkUserEmployeeDuplicatesByUserCompany($tenantId);
            $checks[] = $this->checkUserEmployeeDuplicatesByCompanyEmployee($tenantId);
            $checks[] = $this->checkUserEmployeeInvalidCompanyEmployeeMapping($tenantId);
            $checks[] = $this->checkUserEmployeeMissingCompanyUserMapping($tenantId);
        }

        $checks[] = $this->checkOrphanPivots();
        $checks[] = $this->checkUsersWithoutRoles();
        $checks[] = $this->checkUnknownMenuPermissions();

        return new AuditReportData(
            summary: $this->buildSummary($checks),
            checks: $checks,
            tenant_ids: $resolvedTenantIds,
            fix: $fix,
            verbose: $verbose,
            generated_at: now()->toDateTimeString(),
        );
    }

    /**
     * @param array<int, int|string> $tenantIds
     * @return array<int, int>
     */
    private function resolveTenantIds(array $tenantIds): array
    {
        $normalized = collect($tenantIds)
            ->filter(static fn (mixed $tenantId): bool => is_numeric($tenantId) && (int) $tenantId > 0)
            ->map(static fn (mixed $tenantId): int => (int) $tenantId)
            ->unique()
            ->values();

        if ($normalized->isNotEmpty()) {
            return TenantGroup::query()
                ->whereIn('id', $normalized->all())
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
        }

        return TenantGroup::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function checkCompaniesMissingTenantGroupId(): AuditCheckResultData
    {
        $query = Company::query()
            ->where(static function (Builder $builder): void {
                $builder
                    ->whereNull('tenant_group_id')
                    ->orWhere('tenant_group_id', 0);
            })
            ->orderBy('id');

        $rows = (clone $query)
            ->get(['id', 'tenant_group_id', 'name', 'active'])
            ->map(static fn (Company $company): array => [
                'id' => (int) $company->id,
                'tenant_group_id' => $company->tenant_group_id !== null ? (int) $company->tenant_group_id : null,
                'name' => (string) $company->name,
                'active' => (bool) $company->active,
            ])
            ->take(20)
            ->values()
            ->all();

        return $this->makeCheck(
            id: 'A1',
            title: 'Companies missing tenant_group_id',
            severity: 'fail',
            entity: 'companies',
            count: (clone $query)->count(),
            sampleRows: $rows,
            hint: 'Minden company rekordnak érvényes tenant_group_id kell. Futtasd a tenancy backfillt vagy javítsd kézzel az adatokat.',
        );
    }

    private function checkUserEmployeeDuplicatesByUserCompany(int $tenantId): AuditCheckResultData
    {
        $query = UserEmployee::query()
            ->join('companies', 'companies.id', '=', 'user_employee.company_id')
            ->where('companies.tenant_group_id', $tenantId)
            ->groupBy('user_employee.user_id', 'user_employee.company_id')
            ->selectRaw('user_employee.user_id, user_employee.company_id, COUNT(*) as aggregate')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('user_employee.company_id')
            ->orderBy('user_employee.user_id');

        $rows = (clone $query)
            ->get()
            ->map(static fn (object $row): array => [
                'tenant_group_id' => $tenantId,
                'user_id' => (int) $row->user_id,
                'company_id' => (int) $row->company_id,
                'count' => (int) $row->aggregate,
            ])
            ->take(20)
            ->values()
            ->all();

        return $this->makeCheck(
            id: "B3.tenant_{$tenantId}",
            title: "user_employee duplicate (user_id, company_id) in tenant {$tenantId}",
            severity: 'fail',
            entity: 'user_employee',
            count: (clone $query)->get()->count(),
            sampleRows: $rows,
            hint: 'Egy user egy companyn belül legfeljebb egy employee-hoz tartozhat. Tisztítsd a duplikált pivot rekordokat.',
        );
    }

    private function checkUserEmployeeDuplicatesByCompanyEmployee(int $tenantId): AuditCheckResultData
    {
        $query = UserEmployee::query()
            ->join('companies', 'companies.id', '=', 'user_employee.company_id')
            ->where('companies.tenant_group_id', $tenantId)
            ->groupBy('user_employee.company_id', 'user_employee.employee_id')
            ->selectRaw('user_employee.company_id, user_employee.employee_id, COUNT(*) as aggregate')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('user_employee.company_id')
            ->orderBy('user_employee.employee_id');

        $rows = (clone $query)
            ->get()
            ->map(static fn (object $row): array => [
                'tenant_group_id' => $tenantId,
                'company_id' => (int) $row->company_id,
                'employee_id' => (int) $row->employee_id,
                'count' => (int) $row->aggregate,
            ])
            ->take(20)
            ->values()
            ->all();

        return $this->makeCheck(
            id: "B4.tenant_{$tenantId}",
            title: "user_employee duplicate (company_id, employee_id) in tenant {$tenantId}",
            severity: 'fail',
            entity: 'user_employee',
            count: (clone $query)->get()->count(),
            sampleRows: $rows,
            hint: 'Egy employee egy companyn belül csak egy userhez tartozhat. Vizsgáld felül a user_employee rekordokat.',
        );
    }

    private function checkUserEmployeeInvalidCompanyEmployeeMapping(int $tenantId): AuditCheckResultData
    {
        $query = UserEmployee::query()
            ->leftJoin('company_employee as company_employee_match', static function ($join): void {
                $join
                    ->on('company_employee_match.company_id', '=', 'user_employee.company_id')
                    ->on('company_employee_match.employee_id', '=', 'user_employee.employee_id');
            })
            ->join('companies', 'companies.id', '=', 'user_employee.company_id')
            ->where('companies.tenant_group_id', $tenantId)
            ->whereNull('company_employee_match.id')
            ->orderBy('user_employee.company_id')
            ->orderBy('user_employee.user_id')
            ->select([
                'user_employee.id',
                'user_employee.user_id',
                'user_employee.company_id',
                'user_employee.employee_id',
                'user_employee.active',
            ]);

        $rows = (clone $query)
            ->limit(20)
            ->get()
            ->map(static fn (UserEmployee $row): array => [
                'tenant_group_id' => $tenantId,
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'company_id' => (int) $row->company_id,
                'employee_id' => (int) $row->employee_id,
                'active' => (bool) $row->active,
            ])
            ->values()
            ->all();

        return $this->makeCheck(
            id: "B5.tenant_{$tenantId}",
            title: "user_employee missing company_employee mapping in tenant {$tenantId}",
            severity: 'fail',
            entity: 'user_employee',
            count: (clone $query)->count(),
            sampleRows: $rows,
            hint: 'A user_employee párosnak szerepelnie kell a company_employee pivotban is. Futtass cleanupot vagy deaktiváld a hibás rekordokat.',
        );
    }

    private function checkUserEmployeeMissingCompanyUserMapping(int $tenantId): AuditCheckResultData
    {
        $query = UserEmployee::query()
            ->leftJoin('company_user as company_user_match', static function ($join): void {
                $join
                    ->on('company_user_match.company_id', '=', 'user_employee.company_id')
                    ->on('company_user_match.user_id', '=', 'user_employee.user_id');
            })
            ->join('companies', 'companies.id', '=', 'user_employee.company_id')
            ->where('companies.tenant_group_id', $tenantId)
            ->whereNull('company_user_match.id')
            ->orderBy('user_employee.company_id')
            ->orderBy('user_employee.user_id')
            ->select([
                'user_employee.id',
                'user_employee.user_id',
                'user_employee.company_id',
                'user_employee.employee_id',
                'user_employee.active',
            ]);

        $rows = (clone $query)
            ->limit(20)
            ->get()
            ->map(static fn (UserEmployee $row): array => [
                'tenant_group_id' => $tenantId,
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'company_id' => (int) $row->company_id,
                'employee_id' => (int) $row->employee_id,
                'active' => (bool) $row->active,
            ])
            ->values()
            ->all();

        return $this->makeCheck(
            id: "B6.tenant_{$tenantId}",
            title: "user_employee missing company_user mapping in tenant {$tenantId}",
            severity: 'warn',
            entity: 'user_employee',
            count: (clone $query)->count(),
            sampleRows: $rows,
            hint: 'A user_employee rekordhoz általában tartoznia kell company_user kapcsolatnak is. Ellenőrizd a company hozzáférés és assignment konzisztenciáját.',
        );
    }

    private function checkCompanyEmployeeDuplicates(int $tenantId): AuditCheckResultData
    {
        $query = CompanyEmployee::query()
            ->join('companies', 'companies.id', '=', 'company_employee.company_id')
            ->where('companies.tenant_group_id', $tenantId)
            ->groupBy('company_employee.company_id', 'company_employee.employee_id')
            ->selectRaw('company_employee.company_id, company_employee.employee_id, COUNT(*) as aggregate')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('company_employee.company_id')
            ->orderBy('company_employee.employee_id');

        $rows = (clone $query)
            ->get()
            ->map(static fn (object $row): array => [
                'tenant_group_id' => $tenantId,
                'company_id' => (int) $row->company_id,
                'employee_id' => (int) $row->employee_id,
                'count' => (int) $row->aggregate,
            ])
            ->take(20)
            ->values()
            ->all();

        return $this->makeCheck(
            id: "B1.tenant_{$tenantId}",
            title: "company_employee duplicate (company_id, employee_id) in tenant {$tenantId}",
            severity: 'warn',
            entity: 'company_employee',
            count: (clone $query)->get()->count(),
            sampleRows: $rows,
            hint: 'A company_employee pivot elvileg unique(company_id, employee_id). Ha itt találat van, a séma vagy az adat sérült.',
        );
    }

    private function checkCompanyUserDuplicates(int $tenantId): AuditCheckResultData
    {
        $query = CompanyUser::query()
            ->join('companies', 'companies.id', '=', 'company_user.company_id')
            ->where('companies.tenant_group_id', $tenantId)
            ->groupBy('company_user.company_id', 'company_user.user_id')
            ->selectRaw('company_user.company_id, company_user.user_id, COUNT(*) as aggregate')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('company_user.company_id')
            ->orderBy('company_user.user_id');

        $rows = (clone $query)
            ->get()
            ->map(static fn (object $row): array => [
                'tenant_group_id' => $tenantId,
                'company_id' => (int) $row->company_id,
                'user_id' => (int) $row->user_id,
                'count' => (int) $row->aggregate,
            ])
            ->take(20)
            ->values()
            ->all();

        return $this->makeCheck(
            id: "B2.tenant_{$tenantId}",
            title: "company_user duplicate (company_id, user_id) in tenant {$tenantId}",
            severity: 'warn',
            entity: 'company_user',
            count: (clone $query)->get()->count(),
            sampleRows: $rows,
            hint: 'A company_user pivot elvileg unique(company_id, user_id). Ha mégis van duplikáció, tisztítsd az adatot és ellenőrizd a sémát.',
        );
    }

    private function checkOrphanPivots(): AuditCheckResultData
    {
        $sampleRows = [];

        $userEmployeeOrphans = UserEmployee::query()
            ->where(static function (Builder $builder): void {
                $builder
                    ->doesntHave('user')
                    ->orWhereDoesntHave('company')
                    ->orWhereDoesntHave('employee');
            })
            ->orderBy('id')
            ->get()
            ->map(static function (UserEmployee $row): array {
                return [
                    'pivot' => 'user_employee',
                    'id' => (int) $row->id,
                    'user_id' => (int) $row->user_id,
                    'company_id' => (int) $row->company_id,
                    'employee_id' => (int) $row->employee_id,
                    'missing' => implode(',', array_filter([
                        $row->user()->exists() ? null : 'user',
                        $row->company()->exists() ? null : 'company',
                        $row->employee()->exists() ? null : 'employee',
                    ])),
                ];
            });

        $companyUserOrphans = CompanyUser::query()
            ->where(static function (Builder $builder): void {
                $builder
                    ->doesntHave('user')
                    ->orWhereDoesntHave('company');
            })
            ->orderBy('id')
            ->get()
            ->map(static function (CompanyUser $row): array {
                return [
                    'pivot' => 'company_user',
                    'id' => (int) $row->id,
                    'user_id' => (int) $row->user_id,
                    'company_id' => (int) $row->company_id,
                    'employee_id' => null,
                    'missing' => implode(',', array_filter([
                        $row->user()->exists() ? null : 'user',
                        $row->company()->exists() ? null : 'company',
                    ])),
                ];
            });

        $companyEmployeeOrphans = CompanyEmployee::query()
            ->where(static function (Builder $builder): void {
                $builder
                    ->doesntHave('employee')
                    ->orWhereDoesntHave('company');
            })
            ->orderBy('id')
            ->get()
            ->map(static function (CompanyEmployee $row): array {
                return [
                    'pivot' => 'company_employee',
                    'id' => (int) $row->id,
                    'user_id' => null,
                    'company_id' => (int) $row->company_id,
                    'employee_id' => (int) $row->employee_id,
                    'missing' => implode(',', array_filter([
                        $row->company()->exists() ? null : 'company',
                        $row->employee()->exists() ? null : 'employee',
                    ])),
                ];
            });

        $allRows = $userEmployeeOrphans
            ->concat($companyUserOrphans)
            ->concat($companyEmployeeOrphans)
            ->values();

        $sampleRows = $allRows->take(20)->all();

        return $this->makeCheck(
            id: 'C1',
            title: 'Orphan pivot rows',
            severity: 'fail',
            entity: 'pivot',
            count: $allRows->count(),
            sampleRows: $sampleRows,
            hint: 'A pivot rekordok nem mutathatnak hiányzó user/company/employee rekordokra. Ellenőrizd a régi adattisztítást és a sémát.',
        );
    }

    private function checkUsersWithoutRoles(): AuditCheckResultData
    {
        $superadminEmail = (string) config('seeding.superadmin_email', 'superadmin@shift-smith.com');

        $query = User::query()
            ->whereDoesntHave('roles')
            ->where('email', '!=', $superadminEmail)
            ->orderBy('id');

        $rows = (clone $query)
            ->limit(20)
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ])
            ->values()
            ->all();

        return $this->makeCheck(
            id: 'D1',
            title: 'Users without any role',
            severity: 'warn',
            entity: 'users',
            count: (clone $query)->count(),
            sampleRows: $rows,
            hint: 'A felhasználókhoz legyen legalább egy role rendelve. A superadmin kivétel csak seedelt fallback esetben elfogadható.',
        );
    }

    private function checkUnknownMenuPermissions(): AuditCheckResultData
    {
        $known = Permission::query()
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        $unknown = collect(MenuPermissions::collect())
            ->reject(static fn (string $permission): bool => in_array($permission, $known, true))
            ->values();

        return $this->makeCheck(
            id: 'D2',
            title: 'Unknown permission strings in menu can definitions',
            severity: 'warn',
            entity: 'menu',
            count: $unknown->count(),
            sampleRows: $unknown
                ->take(20)
                ->map(static fn (string $permission): array => ['can' => $permission])
                ->all(),
            hint: 'A menüben csak létező permission string szerepeljen, különben a frontend és backend gate eltérhet.',
        );
    }

    private function checkTenantContextConsistency(int $tenantId): AuditCheckResultData
    {
        $currentTenantId = TenantGroup::current()?->id;
        $count = 0;
        $sampleRows = [];

        if (is_numeric($currentTenantId) && (int) $currentTenantId > 0 && (int) $currentTenantId !== $tenantId) {
            $count = 1;
            $sampleRows[] = [
                'requested_tenant_id' => $tenantId,
                'current_tenant_id' => (int) $currentTenantId,
            ];
        }

        return $this->makeCheck(
            id: "A2.tenant_{$tenantId}",
            title: "Tenant context sanity check for tenant {$tenantId}",
            severity: 'warn',
            entity: 'tenancy',
            count: $count,
            sampleRows: $sampleRows,
            hint: 'Ha TenantGroup::current() be van állítva, annak egyeznie kell a futtatott tenant paraméterrel. Eltérés esetén a scope audit félrevezető lehet.',
        );
    }

    /**
     * @param array<int, AuditCheckResultData> $checks
     * @return array{ok:int,warn:int,fail:int,total:int}
     */
    private function buildSummary(array $checks): array
    {
        $summary = [
            'ok' => 0,
            'warn' => 0,
            'fail' => 0,
            'total' => count($checks),
        ];

        foreach ($checks as $check) {
            if ($check->count === 0) {
                $summary['ok']++;
                continue;
            }

            if ($check->severity === 'fail') {
                $summary['fail']++;
                continue;
            }

            if ($check->severity === 'warn') {
                $summary['warn']++;
                continue;
            }

            $summary['ok']++;
        }

        return $summary;
    }

    /**
     * @param array<int, array<string, scalar|null>> $sampleRows
     */
    private function makeCheck(
        string $id,
        string $title,
        string $severity,
        string $entity,
        int $count,
        array $sampleRows,
        string $hint,
    ): AuditCheckResultData {
        return new AuditCheckResultData(
            id: $id,
            title: $title,
            severity: $severity,
            entity: $entity,
            count: $count,
            sample_rows: $sampleRows,
            hint: $hint,
        );
    }
}

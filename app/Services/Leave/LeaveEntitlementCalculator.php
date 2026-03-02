<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Data\Employee\EmployeeLeaveProfileDTO;
use App\Data\Leave\AnnualLeaveEntitlementResult;
use App\Interfaces\EmployeeRepositoryInterface;
use App\Interfaces\EmployeeProfileRepositoryInterface;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Services\Company\CurrentCompanyResolver;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Facades\Settings;

final class LeaveEntitlementCalculator
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employees,
        private readonly EmployeeProfileRepositoryInterface $profiles,
        private readonly CurrentCompanyResolver $currentCompanyResolver,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function calculateAnnualMinutesForEmployee(int $employeeId, int $year): AnnualLeaveEntitlementResult
    {
        $companyId = $this->currentCompanyResolver->resolveCompanyId();

        if ($companyId === null) {
            throw new DomainException('Current company context is required for annual leave entitlement calculation.');
        }

        $tenantGroupId = $this->currentCompanyResolver->resolveTenantGroupId() ?? 0;
        $version = $this->cacheVersionService->get("leave_entitlement:{$companyId}:{$employeeId}:all");
        $settingsVersion = $this->cacheVersionService->get('landlord:app_settings.show');

        /** @var AnnualLeaveEntitlementResult */
        return $this->cacheService->remember(
            tag: "leave_entitlement:{$companyId}:{$employeeId}:{$year}",
            key: implode(':', [
                'tenant', (string) $tenantGroupId,
                'company', (string) $companyId,
                'employee', (string) $employeeId,
                'year', (string) $year,
                'v', (string) $version,
                'settings', (string) $settingsVersion,
            ]),
            callback: function () use ($companyId, $employeeId, $year): AnnualLeaveEntitlementResult {
                try {
                    $employee = $this->employees->findLeaveEntitlementData($employeeId, $companyId);
                } catch (ModelNotFoundException) {
                    throw new DomainException('The employee is not available in the current company scope.');
                }

                $profile = $this->profiles->findByEmployeeInCompany($companyId, $employeeId)
                    ?? new EmployeeLeaveProfileDTO(
                        employee_id: $employeeId,
                        company_id: $companyId,
                        children_count: 0,
                        disabled_children_count: 0,
                        is_disabled: false,
                    );

                return $this->buildResult($employee, $profile, $year);
            },
            ttl: $this->ttl(),
        );
    }

    private function buildResult(
        \App\Data\Employee\EmployeeLeaveEntitlementData $employee,
        EmployeeLeaveProfileDTO $profile,
        int $year
    ): AnnualLeaveEntitlementResult
    {
        $ageAtStartOfYear = $this->ageAtStartOfYear($employee->birth_date, $year);

        $baseMinutes = max(0, LeaveSettings::baseMinutes());
        $ageBonusMinutes = $employee->birth_date === null ? 0 : LeaveSettings::ageBonusMinutes($ageAtStartOfYear);
        $childBonusMinutes = $this->childBonusMinutes($profile);
        $youthBonusMinutes = $employee->birth_date !== null && $ageAtStartOfYear < 18
            ? max(0, Settings::getInt('leave.youth.extra_minutes', 0))
            : 0;
        $disabilityBonusMinutes = $profile->is_disabled
            ? max(0, Settings::getInt('leave.disability.extra_minutes', 0))
            : 0;

        $breakdown = [
            'base_minutes' => $baseMinutes,
            'age_bonus_minutes' => $ageBonusMinutes,
            'child_bonus_minutes' => $childBonusMinutes,
            'youth_bonus_minutes' => $youthBonusMinutes,
            'disability_bonus_minutes' => $disabilityBonusMinutes,
        ];

        return new AnnualLeaveEntitlementResult(
            employee_id: $employee->employee_id,
            company_id: $employee->company_id,
            year: $year,
            base_minutes: $baseMinutes,
            age_bonus_minutes: $ageBonusMinutes,
            child_bonus_minutes: $childBonusMinutes,
            disability_bonus_minutes: $disabilityBonusMinutes,
            youth_bonus_minutes: $youthBonusMinutes,
            total_minutes: array_sum($breakdown),
            breakdown: $breakdown,
        );
    }

    private function ageAtStartOfYear(?string $birthDate, int $year): int
    {
        if ($birthDate === null) {
            return 0;
        }

        return max(0, (int) floor(
            CarbonImmutable::parse($birthDate)->diffInYears(CarbonImmutable::create($year, 1, 1), false)
        ));
    }

    private function childBonusMinutes(EmployeeLeaveProfileDTO $profile): int
    {
        $table = Settings::get('leave.annual.child_bonus_table', []);

        if (! is_array($table)) {
            return 0;
        }

        $byChildrenCount = $table['by_children_count'] ?? [];
        $disabledExtraPerChild = $table['disabled_child_extra_per_child_minutes'] ?? 0;

        if (! is_array($byChildrenCount)) {
            $byChildrenCount = [];
        }

        $effectiveChildrenCount = max(0, $profile->children_count);
        $lookupKey = $effectiveChildrenCount >= 3 ? '3' : (string) $effectiveChildrenCount;
        $baseBonus = $byChildrenCount[$lookupKey] ?? 0;
        $disabledBonus = max(0, $profile->disabled_children_count) * max(0, (int) $disabledExtraPerChild);

        return max(0, (int) $baseBonus) + $disabledBonus;
    }

    private function ttl(): int
    {
        $ttl = (int) config('cache.ttl_fetch', 180);

        return min(300, max(60, $ttl));
    }
}

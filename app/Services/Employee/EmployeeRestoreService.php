<?php

declare(strict_types=1);

namespace App\Services\Employee;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Employee;
use App\Interfaces\PositionRepositoryInterface;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\Org\PositionOrgLevelService;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A törölt dolgozó visszaállítási folyamat orchestration rétege.
 *
 * A service tenant- és company-scoped repository hívásokat koordinál, valamint gondoskodik
 * a helyreállítás utáni cache invalidációról. HTTP válaszépítést nem végezhet.
 */
final class EmployeeRestoreService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly PositionRepositoryInterface $positionRepository,
        private readonly PositionOrgLevelService $positionOrgLevelService,
        private readonly CacheVersionService $cacheVersionService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Ellenőrzi, hogy létezik-e az emailhez tartozó soft deleted dolgozó az aktuális company scope-ban.
     */
    public function findSoftDeletedByEmail(int $companyId, string $email): ?Employee
    {
        return $this->employeeRepository->findSoftDeletedByEmail($companyId, $email);
    }

    /**
     * Előkészíti a visszaállítási dialógushoz szükséges minimális payloadot.
     *
     * Az aktív email ütközést itt kell blokkolni, mert a create folyamat és a restore folyamat
     * ugyanazt az email egyediségi invariánst osztja meg company scope-on belül.
     *
     * @param int $companyId
     * @param string $email
     * @return array{
     *   restore_available: true,
     *   employee: array{id:int,first_name:string,last_name:string,email:string,deleted_at:string|null},
     *   message: string
     * }|null
     */
    public function prepareRestoreResponse(int $companyId, string $email): ?array
    {
        $normalizedEmail = $this->normalizeEmail($email);

        if ($normalizedEmail === '') {
            return null;
        }

        $activeEmployee = $this->employeeRepository->findActiveByEmail($companyId, $normalizedEmail);
        if ($activeEmployee instanceof Employee) {
            throw ValidationException::withMessages([
                'email' => __('employees.messages.active_email_exists'),
            ]);
        }

        $softDeletedEmployee = $this->employeeRepository->findSoftDeletedByEmail($companyId, $normalizedEmail);
        if (! $softDeletedEmployee instanceof Employee) {
            return null;
        }

        return [
            'restore_available' => true,
            'employee' => [
                'id' => (int) $softDeletedEmployee->id,
                'first_name' => (string) $softDeletedEmployee->first_name,
                'last_name' => (string) $softDeletedEmployee->last_name,
                'email' => (string) $softDeletedEmployee->email,
                'deleted_at' => $softDeletedEmployee->deleted_at?->toDateTimeString(),
            ],
            'message' => __('employees.messages.restore_prompt'),
        ];
    }

    /**
     * Visszaállítja a törölt dolgozót, majd újraépíti a szükséges cache-állapotokat.
     *
     * @param int $companyId
     * @param int $employeeId
     * @param array{
     *   first_name:string,
     *   last_name:string,
     *   email:string,
     *   birth_date:string,
     *   address?:string|null,
     *   position_id?:int|null,
     *   phone?:string|null,
     *   hired_at?:string|null,
     *   active?:bool
     * } $data
     * @param int|null $actorUserId
     * @return Employee
     */
    public function restoreEmployee(int $companyId, int $employeeId, array $data, ?int $actorUserId = null): Employee
    {
        unset($actorUserId);

        $normalizedEmail = $this->normalizeEmail((string) ($data['email'] ?? ''));
        if ($normalizedEmail === '') {
            throw ValidationException::withMessages([
                'email' => 'Az e-mail cím megadása kötelező.',
            ]);
        }

        /** @var Employee $employee */
        $employee = DB::transaction(function () use ($companyId, $employeeId, $data, $normalizedEmail): Employee {
            $restorableEmployee = $this->employeeRepository->findTrashedByIdInCompany($employeeId, $companyId);
            if (! $restorableEmployee instanceof Employee) {
                throw ValidationException::withMessages([
                    'employee' => 'A visszaállítandó dolgozó nem található az aktuális cégben.',
                ]);
            }

            if ($this->normalizeEmail((string) $restorableEmployee->email) !== $normalizedEmail) {
                throw ValidationException::withMessages([
                    'email' => 'A visszaállítás csak az eredeti dolgozó e-mail címével végezhető el.',
                ]);
            }

            $activeEmployee = $this->employeeRepository->findActiveByEmail($companyId, $normalizedEmail);
            if ($activeEmployee instanceof Employee && (int) $activeEmployee->id !== $employeeId) {
                throw ValidationException::withMessages([
                    'email' => __('employees.messages.active_email_exists'),
                ]);
            }

            return $this->employeeRepository->restoreEmployee($companyId, $employeeId, [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $normalizedEmail,
                'birth_date' => $data['birth_date'],
                'address' => $data['address'] ?? null,
                'position_id' => $data['position_id'] ?? null,
                'org_level' => $this->resolveOrgLevel($companyId, $data['position_id'] ?? null),
                'phone' => $data['phone'] ?? null,
                'hired_at' => $data['hired_at'] ?? null,
                'active' => (bool) ($data['active'] ?? true),
            ]);
        });

        $this->invalidateCaches($companyId);

        return $employee;
    }

    /**
     * Az email összehasonlítás előtt egységesíti a whitespace és a kisbetűs alakot.
     */
    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    /**
     * A pozícióból származtatott szervezeti szintet oldja fel a restore művelethez.
     */
    private function resolveOrgLevel(int $companyId, ?int $positionId): string
    {
        if (! \is_int($positionId) || $positionId <= 0) {
            return Employee::ORG_LEVEL_STAFF;
        }

        $position = $this->positionRepository->getPosition($positionId, $companyId);

        return $this->positionOrgLevelService->resolveOrgLevel($companyId, (string) $position->name);
    }

    /**
     * A visszaállítás után bumpolja azokat a tenant-scoped cache namespace-eket,
     * amelyek dolgozó- vagy hierarchia adatot jelenítenek meg.
     */
    private function invalidateCaches(int $companyId): void
    {
        $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
        $employeeBase = CacheNamespaces::tenantEmployees($tenantGroupId, $companyId);
        $orgBase = CacheNamespaces::tenantOrgHierarchy($tenantGroupId, $companyId);

        $this->cacheVersionService->bump('employees.fetch');
        $this->cacheVersionService->bump('selectors.employees');
        $this->cacheVersionService->bump('selectors.companies');
        $this->cacheVersionService->bump("{$employeeBase}:index");
        $this->cacheVersionService->bump("{$employeeBase}:selector");
        $this->cacheVersionService->bump("{$orgBase}:hierarchy");
        $this->cacheVersionService->bump("{$orgBase}:path");
    }
}

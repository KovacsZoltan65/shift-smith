<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Data\Employee\EmployeeLeaveEntitlementData;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface EmployeeRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, \App\Models\Employee>
     */
    public function fetch(Request $request): LengthAwarePaginator;
    
    public function getEmployee(int $id): Employee;

    public function findByIdInCompany(int $employeeId, int $companyId): ?Employee;

    public function findByIdInCompanyForUpdate(int $employeeId, int $companyId): ?Employee;

    public function findActiveByEmail(int $companyId, string $email): ?Employee;

    public function findSoftDeletedByEmail(int $companyId, string $email): ?Employee;

    public function findTrashedByIdInCompany(int $employeeId, int $companyId): ?Employee;

    public function findLeaveEntitlementData(int $employeeId, int $companyId): EmployeeLeaveEntitlementData;
    
    public function getEmployeeByName(string $name): Employee;

    /**
     * Új dolgozó létrehozása.
     *
     * @param array{
     *   first_name: string,
     *   last_name: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   email?: string|null,
     *   position_id?: int|null,
     *   org_level?: string|null,
     *   hired_at: string|null
     * } $data
     * @return Employee
     */
    public function store(array $data): Employee;
    
    /**
     * Dolgozó frissítése azonosító alapján.
     *
     * @param array{
     *   first_name: string,
     *   last_name: string,
     *   email?: string|null,
     *   address?: string|null,
     *   phone?: string|null,
     *   position_id?: int|null,
     *   org_level?: string|null,
     *   hired_at?: string|null,
     *   active?: bool,
     *   company_id?: int|null
     * } $data
     * @param int $id
     * @return Employee
     */
    public function update(array $data, $id): Employee;
    
    /**
     * @param list<int> $ids
     * @return int
     */
    public function bulkDelete(array $ids): int;
    
    public function destroy(int $id): bool;

    public function softDeleteEmployee(int $companyId, int $employeeId): bool;

    /**
     * @param array{
     *   first_name: string,
     *   last_name: string,
     *   email: string,
     *   birth_date: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   position_id?: int|null,
     *   org_level?: string|null,
     *   hired_at?: string|null,
     *   active?: bool
     * } $data
     */
    public function restoreEmployee(int $companyId, int $employeeId, array $data): Employee;

    public function isCeo(int $companyId, int $employeeId): bool;
    
    /**
     * @param array{
     *   only_active?: bool
     * } $params
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params): array;

    /**
     * @param array{
     *   required_daily_minutes?: int|null,
     *   month?: string|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   search?: string|null,
     *   shift_ids?: list<int>,
     *   eligible_for_autoplan?: bool
     * } $params
     *
     * @return array{
     *   data: array<int, array{id:int, full_name:string, name:string, work_pattern_summary:string}>,
     *   meta: array{
     *     total_employees:int,
     *     eligible_count:int,
     *     excluded_count:int,
     *     excluded_reasons: array{missing_pattern:int, not_matching_minutes:int, inactive:int},
     *     required_daily_minutes:int,
     *     month:string|null
     *   }
     * }
     */
    public function getEligibleForAutoPlan(int $companyId, array $params): array;
}

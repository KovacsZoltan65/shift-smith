<?php

declare(strict_types=1);

namespace App\Interfaces;

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
    
    public function getEmployeeByName(string $name): Employee;

    /**
     * Summary of store
     * @param array{
     *   first_name: string,
     *   last_name: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   email?: string|null,
     *   position_id?: int|null,
     *   hired_at: string|null
     * } $data
     * @return Employee
     */
    public function store(array $data): Employee;
    
    /**
     * Summary of update
     * @param array{
     *   first_name: string,
     *   last_name: string,
     *   email?: string|null,
     *   address?: string|null,
     *   phone?: string|null,
     *   position_id?: int|null,
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
     *   target_daily_minutes?: int|null,
     *   month?: string|null,
     *   shift_ids?: list<int>
     * } $params
     *
     * @return array{
     *   data: array<int, array{id:int, name:string}>,
     *   meta: array{
     *     total_count:int,
     *     eligible_count:int,
     *     excluded_count:int,
     *     breakdown: array{inactive:int, not_target_daily_minutes:int},
     *     target_daily_minutes:int,
     *     month:string|null
     *   }
     * }
     */
    public function getEligibleForAutoPlan(int $companyId, array $params): array;
}

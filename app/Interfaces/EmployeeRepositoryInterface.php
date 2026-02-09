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
}
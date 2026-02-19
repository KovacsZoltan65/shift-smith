<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\WorkPattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface WorkPatternRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, WorkPattern>
     */
    public function fetch(Request $request): LengthAwarePaginator;

    public function getWorkPattern(int $id): WorkPattern;

    /**
     * @param array{
     *   company_id:int,
     *   name:string,
     *   type:string,
     *   cycle_length_days?:int|null,
     *   weekly_minutes?:int|null,
     *   active?:bool,
     *   meta?:array<string,mixed>|null
     * } $data
     */
    public function store(array $data): WorkPattern;

    /**
     * @param array{
     *   company_id:int,
     *   name:string,
     *   type:string,
     *   cycle_length_days?:int|null,
     *   weekly_minutes?:int|null,
     *   active?:bool,
     *   meta?:array<string,mixed>|null
     * } $data
     */
    public function update(array $data, mixed $id): WorkPattern;

    /**
     * @param list<int> $ids
     * @return int
     */
    public function bulkDelete(array $ids): int;

    public function destroy(int $id): bool;

    /**
     * @param int $companyId
     * @param bool $onlyActive
     * @return array<int, array{id:int, name:string, type:string}>
     */
    public function getToSelect(int $companyId, bool $onlyActive = true): array;

    /**
     * @param int $workPatternId
     * @return array<int, array{
     *   id:int,
     *   employee_id:int,
     *   name:string,
     *   email:?string,
     *   phone:?string,
     *   date_from:string,
     *   date_to:?string,
     *   is_primary:bool
     * }>
     */
    public function getAssignedEmployees(int $workPatternId): array;
}

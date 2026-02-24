<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\WorkShift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface WorkShiftRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, WorkShift>
     */
    public function fetch(Request $request): LengthAwarePaginator;
    
    public function getWorkShift(int $id, int $companyId): WorkShift;
    
    /**
     * Summary of store
     * @param array{
     *    company_id: int,
     *    name: string,
     *    start_time?: string|null,
     *    end_time?: string|null,
     *    break_minutes?: int|null,
     *    work_time_minutes?: int|null,
     *    is_flexible?: bool,
     *    active: boolean
     * } $data
     * @return WorkShift
     */
    public function store(array $data): WorkShift;
    
    /**
     * Summary of update
     * @param array{
     *    company_id: int,
     *    name: string,
     *    start_time?: string|null,
     *    end_time?: string|null,
     *    break_minutes?: int|null,
     *    work_time_minutes?: int|null,
     *    is_flexible?: bool,
     *    active: boolean
     * } $data
     * @param int $id
     * @return WorkShift
     */
    public function update(array $data, $id): WorkShift;
    
    /**
     * @param list<int> $ids
     * @return int
     */
    public function bulkDelete(array $ids, int $companyId): int;

    public function destroy(int $id, int $companyId): bool;
    
    /**
     * @param array{
     *   company_id: int,
     *   search?: ?string,
     *   only_active?: bool,
     *   limit?: int
     * } $params
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params): array;
}

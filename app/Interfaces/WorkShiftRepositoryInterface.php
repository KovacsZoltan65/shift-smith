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
    public function paginate(Request $request, int $companyId): LengthAwarePaginator;

    public function findOrFailScoped(int $id, int $companyId): WorkShift;
    
    /**
     * Summary of store
     * @param array{
     *    company_id: int,
     *    name: string,
     *    start_time?: string|null,
     *    end_time?: string|null,
     *    break_minutes?: int|null,
     *    work_time_minutes?: int|null,
     *    breaks?: list<array{break_start_time:string,break_end_time:string,break_minutes:int}>,
     *    active: boolean
     * } $data
     * @return WorkShift
     */
    public function store(array $data): WorkShift;
    
    /**
     * Summary of update
     * @param array{
     *    name: string,
     *    start_time?: string|null,
     *    end_time?: string|null,
     *    break_minutes?: int|null,
     *    work_time_minutes?: int|null,
     *    breaks?: list<array{break_start_time:string,break_end_time:string,break_minutes:int}>,
     *    active: boolean
     * } $data
     * @param WorkShift $shift
     * @return WorkShift
     */
    public function update(WorkShift $shift, array $data): WorkShift;
    
    /**
     * @param list<int> $ids
     * @return int
     */
    public function bulkDelete(array $ids, int $companyId): int;

    public function destroy(WorkShift $shift): void;
    
    /**
     * @param array{
     *   search?: ?string,
     *   only_active?: bool,
     *   limit?: int
     * } $params
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params, int $companyId): array;
}

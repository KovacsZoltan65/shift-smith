<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\WorkShift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface WorkShiftRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, \App\Models\Company>
     */
    public function fetch(Request $request): LengthAwarePaginator;
    
    public function getWorkShift(int $id): WorkShift;

    public function getWorkShiftByName(string $name): WorkShift;
    
    /**
     * Summary of store
     * @param array{
     *    company_id: int,
     *    name: string,
     *    start_time: string,
     *    end_time: string,
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
     *    start_time: string,
     *    end_time: string,
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
    public function bulkDelete(array $ids): int;
    
    public function destroy(int $id): bool;
    
    /**
     * @param array{
     *   only_with_employees?: bool
     * } $params
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params): array;
}
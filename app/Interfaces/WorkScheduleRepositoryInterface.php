<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\WorkSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface WorkScheduleRepositoryInterface
{
    public function fetch(Request $request): LengthAwarePaginator;

    /**
     * @param array{
     *   company_id:int,
     *   name:string,
     *   date_from:string,
     *   date_to:string,
     *   status:string
     * } $data
     */
    public function store(array $data): WorkSchedule;

    /**
     * @param array{
     *   company_id:int,
     *   name:string,
     *   date_from:string,
     *   date_to:string,
     *   status:string
     * } $data
     */
    public function update(array $data, int $id): WorkSchedule;

    public function getWorkSchedule(int $id, int $companyId): WorkSchedule;

    public function destroy(int $id, int $companyId): bool;

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids, int $companyId): int;

    /**
     * @return array<int, array{
     *   id:int,
     *   company_id:int,
     *   name:string,
     *   date_from:string,
     *   date_to:string,
     *   status:string
     * }>
     */
    public function selector(int $companyId, bool $onlyPublished = false): array;
}

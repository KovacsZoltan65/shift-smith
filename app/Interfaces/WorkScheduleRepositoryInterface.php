<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\WorkSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface WorkScheduleRepositoryInterface
{
    /** @return LengthAwarePaginator<int, WorkSchedule> */
    public function fetch(Request $request, int $companyId): LengthAwarePaginator;

    public function findOrFailScoped(int $id, int $companyId): WorkSchedule;

    /**
     * @param array{
     *   name: string,
     *   date_from: string,
     *   date_to: string,
     *   status: string
     * } $data
     * @return WorkSchedule
     */
    public function store(array $data, int $companyId): WorkSchedule;

    /**
     * @param array{
     *   name: string,
     *   date_from: string,
     *   date_to: string,
     *   status: string
     * } $data
     * @return WorkSchedule
     */
    public function update(array $data, int $id, int $companyId): WorkSchedule;

    /** @param list<int> $ids */
    public function bulkDelete(array $ids, int $companyId): int;

    public function destroy(int $id, int $companyId): bool;
}

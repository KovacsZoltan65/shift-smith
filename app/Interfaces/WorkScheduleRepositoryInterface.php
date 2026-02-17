<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\WorkSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface WorkScheduleRepositoryInterface
{
    /** @return LengthAwarePaginator<int, WorkSchedule> */
    public function fetch(Request $request): LengthAwarePaginator;

    public function getWorkSchedule(int $id): WorkSchedule;

    /**
     * @param array{
     *   company_id: int,
     *   name: string,
     *   date_from: string,
     *   date_to: string,
     *   status: string,
     *   notes?: string|null
     * } $data
     * @return WorkSchedule
     */
    public function store(array $data): WorkSchedule;

    /**
     * @param array{
     *   company_id: int,
     *   name: string,
     *   date_from: string,
     *   date_to: string,
     *   status: string,
     *   notes?: string|null
     * } $data
     * @param int $id
     * @return WorkSchedule
     */
    public function update(array $data, int $id): WorkSchedule;

    /** @param list<int> $ids */
    public function bulkDelete(array $ids): int;

    public function destroy(int $id): bool;
}

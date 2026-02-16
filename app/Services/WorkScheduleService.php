<?php

namespace App\Services;

use App\Interfaces\WorkScheduleRepositoryInterface;
use App\Models\WorkSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class WorkScheduleService
{
    public function __construct(
        private readonly WorkScheduleRepositoryInterface $repo
    ) {}

    /**
     * @return LengthAwarePaginator<int, WorkSchedule>
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }

    public function getWorkSchedule(int $id): WorkSchedule
    {
        return $this->repo->getWorkSchedule($id);
    }

    /**
     * @param array{
     *   company_id:int,
     *   name:string,
     *   date_from:string,
     *   date_to:string,
     *   status:string,
     *   notes?:string|null
     * } $data
     */
    public function store(array $data): WorkSchedule
    {
        return $this->repo->store($data);
    }

    /**
     * @param array{
     *   company_id:int,
     *   name:string,
     *   date_from:string,
     *   date_to:string,
     *   status:string,
     *   notes?:string|null
     * } $data
     */
    public function update(array $data, int $id): WorkSchedule
    {
        return $this->repo->update($data, $id);
    }

    /** @param list<int> $ids */
    public function bulkDelete(array $ids): int
    {
        $ids = array_values(array_unique($ids));
        return (int) $this->repo->bulkDelete($ids);
    }

    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }
}

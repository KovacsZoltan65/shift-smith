<?php

namespace App\Services;

use App\Interfaces\WorkShiftRepositoryInterface;
use App\Models\WorkShift;
use Illuminate\Http\Request;

class WorkShiftService
{
    public function __construct(
        private readonly WorkShiftRepositoryInterface $repo
    ) {}

    public function fetch(Request $request)
    {
        return $this->repo->fetch($request);
    }

    /**
     * Summary of getWorkShift
     * @param int $id
     * @return \App\Models\WorkShift
     */
    public function getWorkShift(int $id): WorkShift
    {
        return $this->repo->getWorkShift($id);
    }

    /**
     * Summary of getWorkShiftByName
     * @param string $name
     * @return WorkShift
     */
    public function getWorkShiftByName(string $name): WorkShift
    {
        return $this->repo->getWorkShiftByName($name);
    }

    /**
     * Summary of update
     * @param array{
     *    company_id: int,
     *    name: string,
     *    start_time: string,
     *    end_time: string,
     *    active: boolean
     * } $data
     * @return WorkShift
     */
    public function store(array $data): WorkShift
    {
        return $this->repo->store($data);
    }

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
    public function update(array $data, $id): WorkShift
    {
        return $this->repo->update($data, $id);
    }

    /**
     * @param list<int> $ids
     * @return int
     */
    public function bulkDelete(array $ids): int
    {
        // opcionális tisztítás: nullok/duplikátumok kiszűrése
        $ids = array_values(array_unique($ids));
        
        return (int) $this->repo->bulkDelete($ids);
    }

    /**
     * Summary of destroy
     * @param int $id
     * @return bool
     */
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }

    /**
     * @param array{
     *   only_with_employees?: bool
     * } $params
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params): array
    {
        return $this->repo->getToSelect($params);
    }
}
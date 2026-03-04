<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\WorkSchedule\WorkScheduleData;
use App\Interfaces\WorkScheduleRepositoryInterface;
use App\Models\WorkSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class WorkScheduleService
{
    public function __construct(
        private readonly WorkScheduleRepositoryInterface $repository,
    ) {}

    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repository->fetch($request);
    }

    public function find(int $id, int $companyId): WorkSchedule
    {
        return $this->repository->getWorkSchedule($id, $companyId);
    }

    public function store(WorkScheduleData $data): WorkScheduleData
    {
        $workSchedule = $this->repository->store([
            'company_id' => $data->company_id,
            'name' => $data->name,
            'date_from' => $data->date_from,
            'date_to' => $data->date_to,
            'status' => $data->status,
        ]);

        return WorkScheduleData::fromModel($workSchedule);
    }

    public function update(int $id, WorkScheduleData $data): WorkScheduleData
    {
        $workSchedule = $this->repository->update([
            'company_id' => $data->company_id,
            'name' => $data->name,
            'date_from' => $data->date_from,
            'date_to' => $data->date_to,
            'status' => $data->status,
        ], $id);

        return WorkScheduleData::fromModel($workSchedule);
    }

    public function destroy(int $id, int $companyId): bool
    {
        return $this->repository->destroy($id, $companyId);
    }

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids, int $companyId): int
    {
        return $this->repository->bulkDelete(array_values(array_unique($ids)), $companyId);
    }

    /**
     * @return array<int, array{id:int, company_id:int, name:string, date_from:string, date_to:string, status:string}>
     */
    public function selector(int $companyId, bool $onlyPublished = false): array
    {
        return $this->repository->selector($companyId, $onlyPublished);
    }
}

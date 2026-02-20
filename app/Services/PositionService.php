<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Position\PositionData;
use App\Interfaces\PositionRepositoryInterface;
use App\Models\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class PositionService
{
    public function __construct(
        private readonly PositionRepositoryInterface $repo
    ) {}

    /**
     * @return LengthAwarePaginator<int, Position>
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }

    public function find(int $id, int $companyId): Position
    {
        return $this->repo->getPosition($id, $companyId);
    }

    public function store(PositionData $data): PositionData
    {
        $position = $this->repo->store([
            'company_id' => $data->company_id,
            'name' => $data->name,
            'description' => $data->description,
            'active' => $data->active,
        ]);

        return PositionData::fromModel($position);
    }

    public function update(int $id, PositionData $data): PositionData
    {
        $position = $this->repo->update([
            'company_id' => $data->company_id,
            'name' => $data->name,
            'description' => $data->description,
            'active' => $data->active,
        ], $id);

        return PositionData::fromModel($position);
    }

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids, int $companyId): int
    {
        $ids = array_values(array_unique($ids));
        return $this->repo->bulkDelete($ids, $companyId);
    }

    public function destroy(int $id, int $companyId): bool
    {
        return $this->repo->destroy($id, $companyId);
    }

    /**
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(int $companyId, bool $onlyActive = true): array
    {
        return $this->repo->getToSelect($companyId, $onlyActive);
    }
}

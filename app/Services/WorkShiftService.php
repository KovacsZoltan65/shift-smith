<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\WorkShiftRepositoryInterface;
use App\Models\WorkShift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

final class WorkShiftService
{
    public function __construct(
        private readonly WorkShiftRepositoryInterface $repo
    ) {}

    /**
     * @return LengthAwarePaginator<int, WorkShift>
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }

    public function find(int $id, int $companyId): WorkShift
    {
        return $this->repo->getWorkShift($id, $companyId);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function store(array $data): WorkShift
    {
        return $this->repo->store($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data, int $companyId): WorkShift
    {
        $data['company_id'] = $companyId;

        return $this->repo->update($data, $id);
    }

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids, int $companyId): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return 0;
        }

        return $this->repo->bulkDelete($ids, $companyId);
    }

    public function destroy(int $id, int $companyId): bool
    {
        return $this->repo->destroy($id, $companyId);
    }

    /**
     * @param array{
     *   company_id: int,
     *   search?: ?string,
     *   only_active?: bool,
     *   limit?: int
     * } $params
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params): array
    {
        return $this->repo->getToSelect($params);
    }
}

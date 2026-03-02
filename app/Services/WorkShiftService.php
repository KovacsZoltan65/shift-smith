<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\WorkShiftRepositoryInterface;
use App\Models\WorkShift;
use App\Support\WorkShiftTimeCalculator;
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
    public function index(Request $request, int $companyId): LengthAwarePaginator
    {
        return $this->repo->paginate($request, $companyId);
    }

    /**
     * @return LengthAwarePaginator<int, WorkShift>
     */
    public function fetch(Request $request, int $companyId): LengthAwarePaginator
    {
        return $this->index($request, $companyId);
    }

    public function find(int $id, int $companyId): WorkShift
    {
        return $this->repo->findOrFailScoped($id, $companyId);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function store(array $data, int $companyId): WorkShift
    {
        $data['company_id'] = $companyId;
        $data = $this->prepareComputedPayload($data);

        return $this->repo->store($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data, int $companyId): WorkShift
    {
        $shift = $this->find($id, $companyId);
        $data = $this->prepareComputedPayload($data);

        return $this->repo->update($shift, $data);
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
        $shift = $this->find($id, $companyId);
        $this->repo->destroy($shift);

        return true;
    }

    /**
     * @param array{
     *   search?: ?string,
     *   only_active?: bool,
     *   limit?: int
     * } $params
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params, int $companyId): array
    {
        return $this->repo->getToSelect($params, $companyId);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepareComputedPayload(array $data): array
    {
        $start = (string) ($data['start_time'] ?? '');
        $end = (string) ($data['end_time'] ?? '');
        $window = WorkShiftTimeCalculator::shiftWindow($start, $end);

        $rawBreaks = $data['breaks'] ?? [];
        if (! is_array($rawBreaks)) {
            $rawBreaks = [];
        }

        /** @var list<array{break_start_time:string,break_end_time:string}> $breaks */
        $breaks = array_values(array_filter(array_map(
            static function (mixed $row): ?array {
                if (! is_array($row)) {
                    return null;
                }

                $breakStart = $row['break_start_time'] ?? null;
                $breakEnd = $row['break_end_time'] ?? null;

                if (! is_string($breakStart) || ! is_string($breakEnd)) {
                    return null;
                }

                return [
                    'break_start_time' => $breakStart,
                    'break_end_time' => $breakEnd,
                ];
            },
            $rawBreaks
        )));

        $breakSummary = WorkShiftTimeCalculator::calculateBreaks($breaks, $start, $end);

        unset($data['work_time_minutes'], $data['break_minutes']);
        $data['break_minutes'] = $breakSummary['total'];
        $data['work_time_minutes'] = max(0, $window['duration'] - $breakSummary['total']);
        $data['breaks'] = $breakSummary['rows'];

        return $data;
    }
}

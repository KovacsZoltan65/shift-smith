<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\WorkPattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface WorkPatternRepositoryInterface
{
    /**
     * Munkarendek listázása lapozható formában.
     *
     * @param Request $request Szűrési, rendezési és lapozási paraméterek
     * @return LengthAwarePaginator<int, WorkPattern>
     */
    public function fetch(Request $request): LengthAwarePaginator;

    /**
     * @param array{
     *   company_id:int,
     *   name:string,
     *   daily_work_minutes:int,
     *   break_minutes:int,
     *   core_start_time?:string|null,
     *   core_end_time?:string|null,
     *   active?:bool
     * } $data
     */
    public function store(array $data): WorkPattern;

    /**
     * @param array{
     *   company_id:int,
     *   name:string,
     *   daily_work_minutes:int,
     *   break_minutes:int,
     *   core_start_time?:string|null,
     *   core_end_time?:string|null,
     *   active?:bool
     * } $data
     */
    public function update(array $data, mixed $id): WorkPattern;

    /**
     * @param list<int> $ids
     * @return int
     */
    public function bulkDelete(array $ids, int $companyId): int;

    /**
     * Egy munkarend törlése.
     *
     * @param int $id Munkarend azonosító
     * @return bool
     */
    public function destroy(int $id, int $companyId): bool;

    /**
     * @param int $companyId
     * @param bool $onlyActive
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(int $companyId, bool $onlyActive = true): array;

    /**
     * @param int $workPatternId
     * @return array<int, array{
     *   id:int,
     *   employee_id:int,
     *   name:string,
     *   email:?string,
     *   phone:?string,
     *   date_from:string,
     *   date_to:?string,
     * }>
     */
    public function getAssignedEmployees(int $workPatternId, int $companyId): array;

    public function getWorkPattern(int $id, int $companyId): WorkPattern;
}

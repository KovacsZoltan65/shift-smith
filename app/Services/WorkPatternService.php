<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\WorkPattern\WorkPatternData;
use App\Interfaces\WorkPatternRepositoryInterface;
use App\Models\WorkPattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Munkarend szolgáltatás osztály.
 *
 * Üzleti logikai réteg a munkarendek kezeléséhez.
 * Kezeli a CRUD műveleteket, selector adatokat és a hozzárendelt dolgozók lekérdezését.
 */
class WorkPatternService
{
    /**
     * @param WorkPatternRepositoryInterface $repo Munkarend repository
     */
    public function __construct(
        private readonly WorkPatternRepositoryInterface $repo
    ) {}

    /**
     * Munkarendek listázása lapozással és szűréssel.
     *
     * @param Request $request HTTP kérés
     * @return LengthAwarePaginator<int, WorkPattern> Lapozott munkarend lista
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }

    /**
     * Munkarend lekérése azonosító alapján.
     *
     * @param int $id Munkarend azonosító
     * @return WorkPattern Munkarend model
     */
    public function find(int $id, int $companyId): WorkPattern
    {
        return $this->repo->getWorkPattern($id, $companyId);
    }

    /**
     * Új munkarend létrehozása.
     *
     * @param WorkPatternData $data Munkarend DTO
     * @return WorkPatternData Létrehozott munkarend DTO
     */
    public function store(WorkPatternData $data): WorkPatternData
    {
        $workPattern = $this->repo->store([
            'company_id' => $data->company_id,
            'name' => $data->name,
            'daily_work_minutes' => $data->daily_work_minutes,
            'break_minutes' => $data->break_minutes,
            'core_start_time' => $data->core_start_time,
            'core_end_time' => $data->core_end_time,
            'active' => $data->active,
        ]);

        return WorkPatternData::fromModel($workPattern);
    }

    /**
     * Munkarend frissítése.
     *
     * @param int $id Munkarend azonosító
     * @param WorkPatternData $data Munkarend DTO
     * @return WorkPatternData Frissített munkarend DTO
     */
    public function update(int $id, WorkPatternData $data): WorkPatternData
    {
        $workPattern = $this->repo->update([
            'company_id' => $data->company_id,
            'name' => $data->name,
            'daily_work_minutes' => $data->daily_work_minutes,
            'break_minutes' => $data->break_minutes,
            'core_start_time' => $data->core_start_time,
            'core_end_time' => $data->core_end_time,
            'active' => $data->active,
        ], $id);

        return WorkPatternData::fromModel($workPattern);
    }

    /**
     * Több munkarend törlése egyszerre.
     *
     * @param list<int> $ids Munkarend azonosítók
     * @return int Törölt rekordok száma
     */
    public function bulkDelete(array $ids, int $companyId): int
    {
        $ids = array_values(array_unique($ids));
        return $this->repo->bulkDelete($ids, $companyId);
    }

    /**
     * Egy munkarend törlése.
     *
     * @param int $id Munkarend azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function destroy(int $id, int $companyId): bool
    {
        return $this->repo->destroy($id, $companyId);
    }

    /**
     * Munkarend selector lista lekérése.
     *
     * @param int $companyId Cég azonosító
     * @param bool $onlyActive Csak aktív minták
     * @return array<int, array{id:int, name:string}> Selector lista
     */
    public function getToSelect(int $companyId, bool $onlyActive = true): array
    {
        return $this->repo->getToSelect($companyId, $onlyActive);
    }

    /**
     * Munkarendhez rendelt dolgozók listázása.
     *
     * @param int $workPatternId Munkarend azonosító
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
    public function getAssignedEmployees(int $workPatternId, int $companyId): array
    {
        return $this->repo->getAssignedEmployees($workPatternId, $companyId);
    }
}

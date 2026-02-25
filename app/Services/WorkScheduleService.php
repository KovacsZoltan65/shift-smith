<?php

namespace App\Services;

use App\Data\WorkSchedule\WorkScheduleData;
use App\Interfaces\WorkScheduleRepositoryInterface;
use App\Models\WorkSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Munkabeosztás szolgáltatás osztály
 * 
 * Üzleti logikai réteg a munkabeosztások kezeléséhez.
 * Repository pattern-t használ az adatbázis műveletekhez.
 */
class WorkScheduleService
{
    /**
     * @param WorkScheduleRepositoryInterface $repo Munkabeosztás repository
     */
    public function __construct(
        private readonly WorkScheduleRepositoryInterface $repo
    ) {}

    /**
     * Munkabeosztások listázása lapozással és szűréssel
     * 
     * @param Request $request HTTP kérés (search, field, order, per_page paraméterekkel)
     * @return LengthAwarePaginator<int, WorkSchedule> Lapozott munkabeosztás lista
     */
    public function fetch(Request $request, int $companyId): LengthAwarePaginator
    {
        return $this->repo->fetch($request, $companyId);
    }

    /**
     * Egy munkabeosztás lekérése azonosító alapján
     * 
     * @param int $id Munkabeosztás azonosító
     * @return WorkSchedule Munkabeosztás model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getWorkSchedule(int $id, int $companyId): WorkSchedule
    {
        return $this->repo->findOrFailScoped($id, $companyId);
    }

    /**
     * Munkabeosztás lekérése azonosító alapján (policy-barát model lookup).
     *
     * @param int $id Munkabeosztás azonosító
     * @return WorkSchedule Munkabeosztás model
     */
    public function find(int $id, int $companyId): WorkSchedule
    {
        return $this->repo->findOrFailScoped($id, $companyId);
    }

    /**
     * Új munkabeosztás létrehozása.
     *
     * @param WorkScheduleData $data Validált DTO adatok
     * @return WorkScheduleData Létrehozott munkabeosztás DTO
     */
    public function store(WorkScheduleData $data, int $companyId): WorkScheduleData
    {
        $workSchedule = $this->repo->store([
            'name' => $data->name,
            'date_from' => $data->date_from,
            'date_to' => $data->date_to,
            'status' => $data->status,
        ], $companyId);

        return WorkScheduleData::fromModel($workSchedule);
    }

    /**
     * Munkabeosztás adatainak frissítése.
     *
     * @param int $id Munkabeosztás azonosító
     * @param WorkScheduleData $data Frissítendő DTO adatok
     * @return WorkScheduleData Frissített munkabeosztás DTO
     */
    public function update(int $id, WorkScheduleData $data, int $companyId): WorkScheduleData
    {
        $workSchedule = $this->repo->update([
            'name' => $data->name,
            'date_from' => $data->date_from,
            'date_to' => $data->date_to,
            'status' => $data->status,
        ], $id, $companyId);

        return WorkScheduleData::fromModel($workSchedule);
    }

    /**
     * Több munkabeosztás törlése egyszerre
     * 
     * Automatikusan kiszűri a duplikátumokat.
     * 
     * @param list<int> $ids Munkabeosztás azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    public function bulkDelete(array $ids, int $companyId): int
    {
        $ids = array_values(array_unique($ids));
        return (int) $this->repo->bulkDelete($ids, $companyId);
    }

    /**
     * Egy munkabeosztás törlése
     * 
     * @param int $id Munkabeosztás azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function destroy(int $id, int $companyId): bool
    {
        return $this->repo->destroy($id, $companyId);
    }
}

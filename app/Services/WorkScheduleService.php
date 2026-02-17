<?php

namespace App\Services;

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
    public function __construct(
        private readonly WorkScheduleRepositoryInterface $repo
    ) {}

    /**
     * Munkabeosztások listázása lapozással és szűréssel
     * 
     * @param Request $request HTTP kérés (search, field, order, per_page paraméterekkel)
     * @return LengthAwarePaginator<int, WorkSchedule> Lapozott munkabeosztás lista
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }

    /**
     * Egy munkabeosztás lekérése azonosító alapján
     * 
     * @param int $id Munkabeosztás azonosító
     * @return WorkSchedule Munkabeosztás model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getWorkSchedule(int $id): WorkSchedule
    {
        return $this->repo->getWorkSchedule($id);
    }

    /**
     * Új munkabeosztás létrehozása
     * 
     * @param array{
     *   company_id:int,
     *   name:string,
     *   date_from:string,
     *   date_to:string,
     *   status:string,
     *   notes?:string|null
     * } $data Munkabeosztás adatok
     * @return WorkSchedule Létrehozott munkabeosztás
     */
    public function store(array $data): WorkSchedule
    {
        return $this->repo->store($data);
    }

    /**
     * Munkabeosztás adatainak frissítése
     * 
     * @param array{
     *   company_id:int,
     *   name:string,
     *   date_from:string,
     *   date_to:string,
     *   status:string,
     *   notes?:string|null
     * } $data Frissítendő adatok
     * @param int $id Munkabeosztás azonosító
     * @return WorkSchedule Frissített munkabeosztás
     */
    public function update(array $data, int $id): WorkSchedule
    {
        return $this->repo->update($data, $id);
    }

    /**
     * Több munkabeosztás törlése egyszerre
     * 
     * Automatikusan kiszűri a duplikátumokat.
     * 
     * @param list<int> $ids Munkabeosztás azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    public function bulkDelete(array $ids): int
    {
        $ids = array_values(array_unique($ids));
        return (int) $this->repo->bulkDelete($ids);
    }

    /**
     * Egy munkabeosztás törlése
     * 
     * @param int $id Munkabeosztás azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }
}

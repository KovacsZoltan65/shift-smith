<?php

namespace App\Services;

use App\Interfaces\WorkShiftRepositoryInterface;
use App\Models\WorkShift;
use Illuminate\Http\Request;

/**
 * Műszak szolgáltatás osztály
 * 
 * Üzleti logikai réteg a műszakok kezeléséhez.
 * Repository pattern-t használ az adatbázis műveletekhez.
 */
class WorkShiftService
{
    public function __construct(
        private readonly WorkShiftRepositoryInterface $repo
    ) {}

    /**
     * Műszakok listázása lapozással és szűréssel
     * 
     * @param Request $request HTTP kérés (search, field, order, per_page paraméterekkel)
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<WorkShift> Lapozott műszak lista
     */
    public function fetch(Request $request)
    {
        return $this->repo->fetch($request);
    }

    /**
     * Egy műszak lekérése azonosító alapján
     * 
     * @param int $id Műszak azonosító
     * @return WorkShift Műszak model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getWorkShift(int $id): WorkShift
    {
        return $this->repo->getWorkShift($id);
    }

    /**
     * Műszak lekérése név alapján
     * 
     * @param string $name Műszak neve
     * @return WorkShift Műszak model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getWorkShiftByName(string $name): WorkShift
    {
        return $this->repo->getWorkShiftByName($name);
    }

    /**
     * Új műszak létrehozása
     * 
     * @param array{
     *    company_id: int,
     *    name: string,
     *    start_time: string,
     *    end_time: string,
     *    active: boolean
     * } $data Műszak adatok
     * @return WorkShift Létrehozott műszak
     */
    public function store(array $data): WorkShift
    {
        return $this->repo->store($data);
    }

    /**
     * Műszak adatainak frissítése
     * 
     * @param array{
     *    company_id: int,
     *    name: string,
     *    start_time: string,
     *    end_time: string,
     *    active: boolean
     * } $data Frissítendő adatok
     * @param int $id Műszak azonosító
     * @return WorkShift Frissített műszak
     */
    public function update(array $data, $id): WorkShift
    {
        return $this->repo->update($data, $id);
    }

    /**
     * Több műszak törlése egyszerre
     * 
     * Automatikusan kiszűri a duplikátumokat.
     * 
     * @param list<int> $ids Műszak azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    public function bulkDelete(array $ids): int
    {
        // opcionális tisztítás: nullok/duplikátumok kiszűrése
        $ids = array_values(array_unique($ids));
        
        return (int) $this->repo->bulkDelete($ids);
    }

    /**
     * Egy műszak törlése
     * 
     * @param int $id Műszak azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }

    /**
     * Műszakok lekérése select listához
     * 
     * Egyszerűsített műszak lista (id, name) dropdown/select mezőkhöz.
     * 
     * @param array{
     *   only_with_employees?: bool
     * } $params Szűrési paraméterek
     * @return array<int, array{id:int, name:string}> Műszakok tömbje
     */
    public function getToSelect(array $params): array
    {
        return $this->repo->getToSelect($params);
    }
}
<?php

namespace App\Services;

use App\Interfaces\CompanyRepositoryInterface;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Cég szolgáltatás osztály
 * 
 * Üzleti logikai réteg a cégek kezeléséhez.
 * Repository pattern-t használ az adatbázis műveletekhez.
 */
class CompanyService
{
    public function __construct(
        private readonly CompanyRepositoryInterface $repo
    ) {}
    
    /**
     * Cégek listázása lapozással és szűréssel
     * 
     * @param Request $request HTTP kérés (search, field, order, per_page paraméterekkel)
     * @return LengthAwarePaginator<int, Company> Lapozott cég lista
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }
    
    /**
     * Egy cég lekérése azonosító alapján
     * 
     * @param int $id Cég azonosító
     * @return Company Cég model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getCompany(int $id): Company
    {
        return $this->repo->getCompany($id);
    }
    
    /**
     * Cég lekérése név alapján
     * 
     * @param string $name Cég neve
     * @return Company Cég model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getCompanyByName(string $name): Company
    {
        return $this->repo->getCompanyByName($name);
    }
    
    /**
     * Új cég létrehozása
     * 
     * @param array{
     *   name: string,
     *   email: string,
     *   address: string|null,
     *   phone: string|null
     * } $data Cég adatok
     * @return Company Létrehozott cég
     */
    public function store(array $data): Company
    {
        return $this->repo->store($data);
    }
    
    /**
     * Cég adatainak frissítése
     * 
     * @param array{
     *    name: string,
     *    email: string,
     *    address: string,
     *    phone: string,
     *    active: boolean
     * } $data Frissítendő adatok
     * @param int $id Cég azonosító
     * @return Company Frissített cég
     */
    public function update(array $data, $id): Company
    {
        return $this->repo->update($data, $id);
    }
    
    /**
     * Több cég törlése egyszerre
     * 
     * Automatikusan kiszűri a duplikátumokat.
     * 
     * @param list<int> $ids Cég azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    public function bulkDelete(array $ids): int
    {
        // opcionális tisztítás: nullok/duplikátumok kiszűrése
        $ids = array_values(array_unique($ids));
        
        return (int) $this->repo->bulkDelete($ids);
    }
    
    /**
     * Egy cég törlése
     * 
     * @param int $id Cég azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }
    
    /**
     * Cégek lekérése select listához
     * 
     * Egyszerűsített cég lista (id, name) dropdown/select mezőkhöz.
     * 
     * @param array{
     *   only_with_employees?: bool
     * } $params Szűrési paraméterek
     * @return array<int, array{id:int, name:string}> Cégek tömbje
     */
    public function getToSelect(array $params): array
    {
        return $this->repo->getToSelect($params);
    }
    
}
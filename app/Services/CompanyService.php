<?php

namespace App\Services;

use App\Interfaces\CompanyRepositoryInterface;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use App\Data\Company\CompanyData;

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
     * Model lookup (auth/policy friendly).
     */
    public function find(int $id): Company
    {
        return $this->repo->getCompany($id);
    }

    /**
     * Model lookup by name (auth/policy friendly).
     */
    public function findByName(string $name): Company
    {
        return $this->repo->getCompanyByName($name);
    }

    /**
     * 
     */
public function getById(int $id): CompanyData
    {
        return CompanyData::fromModel($this->repo->getCompany($id));
    }

    public function getByName(string $name): CompanyData
    {
        return CompanyData::fromModel($this->repo->getCompanyByName($name));
    }

    public function store(CompanyData $data): CompanyData
    {
        $company = $this->repo->store([
            'name'    => $data->name,
            'email'   => $data->email,
            'address' => $data->address,
            'phone'   => $data->phone,
            'active'  => $data->active,
        ]);

        return CompanyData::fromModel($company);
    }

    public function update(int $id, CompanyData $data): CompanyData
    {
        $company = $this->repo->update([
            'name'    => $data->name,
            'email'   => $data->email,
            'address' => $data->address,
            'phone'   => $data->phone,
            'active'  => $data->active,
        ], $id);

        return CompanyData::fromModel($company);
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
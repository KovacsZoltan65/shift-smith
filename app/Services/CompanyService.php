<?php

namespace App\Services;

use App\Interfaces\CompanyRepositoryInterface;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use App\Data\Company\CompanyData;

/**
 * Üzleti logika a cégek kezeléséhez.
 */
class CompanyService
{
    public function __construct(
        private readonly CompanyRepositoryInterface $repo
    ) {}
    
    /**
     * Cégek listázása lapozással és szűréssel.
     *
     * @return LengthAwarePaginator<int, Company>
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }

    /**
     * HQ globális (landlord) céglista tenant scope nélkül.
     *
     * @return LengthAwarePaginator<int, Company>
     */
    public function fetchHq(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetchHq($request);
    }

    /**
     * Cég lekérése azonosító alapján.
     */
    public function find(int $id): Company
    {
        return $this->repo->getCompany($id);
    }

    /**
     * Cég lekérése név alapján.
     */
    public function findByName(string $name): Company
    {
        return $this->repo->getCompanyByName($name);
    }

    /**
     * Cég DTO lekérése azonosító alapján.
     */
    public function getById(int $id): CompanyData
    {
        return CompanyData::fromModel($this->repo->getCompany($id));
    }

    /**
     * Cég DTO lekérése név alapján.
     */
    public function getByName(string $name): CompanyData
    {
        return CompanyData::fromModel($this->repo->getCompanyByName($name));
    }

    /**
     * Új cég létrehozása.
     */
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

    /**
     * Cég adatainak frissítése.
     */
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
     * Több cég törlése egyszerre.
     *
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids): int
    {
        $ids = array_values(array_unique($ids));
        
        return (int) $this->repo->bulkDelete($ids);
    }
    
    /**
     * Egy cég törlése.
     */
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }
    
    /**
     * Cégek lekérése select listához.
     *
     * @param array{only_with_employees?: bool} $params
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params): array
    {
        return $this->repo->getToSelect($params);
    }
    
}

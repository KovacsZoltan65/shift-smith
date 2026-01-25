<?php

namespace App\Services;

use App\Interfaces\CompanyRepositoryInterface;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class CompanyService
{
    public function __construct(
        private readonly CompanyRepositoryInterface $repo
    ) {}
    
    /**
     * @param Request $request
     * @return LengthAwarePaginator<int, Company>
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }
    
    /**
     * Summary of getCompany
     * @param int $id
     * @return \App\Models\Company
     */
    public function getCompany(int $id): Company
    {
        return $this->repo->getCompany($id);
    }
    
    /**
     * Summary of store
     * @param array{
     *   name: string,
     *   email: string,
     *   address: string|null,
     *   phone: string|null
     * } $data
     * @return Company
     */
    public function store(array $data): Company
    {
        return $this->repo->store($data);
    }
    
    /**
     * Summary of update
     * @param array{
     *    name: string,
     *    email: string,
     *    address: string,
     *    phone: string,
     *    active: boolean
     * } $data
     * @param int $id
     * @return Company
     */
    public function update(array $data, $id): Company
    {
        return $this->repo->update($data, $id);
    }
    
    /**
     * @param list<int> $ids
     * @return int
     */
    public function bulkDelete(array $ids): int
    {
        // opcionális tisztítás: nullok/duplikátumok kiszűrése
        $ids = array_values(array_unique($ids));
        
        return (int) $this->repo->bulkDelete($ids);
    }
    
    /**
     * Summary of destroy
     * @param int $id
     * @return bool
     */
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }
    
    /**
     * Summary of getToSelect
     * @return array<int, array{id: int, name: string}>
     */
    public function getToSelect(): array
    {
        return $this->repo->getToSelect();
    }
    
}
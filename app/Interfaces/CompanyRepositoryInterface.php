<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface CompanyRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, \App\Models\Company>
     */
    public function fetch(Request $request): LengthAwarePaginator;
    
    public function getCompany(int $id): Company;
    /**
     * Summary of store
     * @param array{
     *   name: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   email?: string|null
     * } $data
     * @return Company
     */
    public function store(array $data): Company;
    
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
    public function update(array $data, $id): Company;
    
    /**
     * @param list<int> $ids
     * @return int
     */
    public function bulkDelete(array $ids): int;
    
    public function destroy(int $id): bool;
    
    /**
     * Summary of getToSelect
     * @return array<int, array{id: int, name: string}>
     */
    public function getToSelect(): array;
}
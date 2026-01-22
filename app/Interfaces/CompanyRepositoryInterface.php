<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface CompanyRepositoryInterface
{
    public function fetch(Request $request): LengthAwarePaginator;
    
    public function getCompany(int $id): Company;
    
    public function store(array $data): Company;
    
    public function update(array $data, $id): Company;
    
    public function bulkDelete(array $ids): int;
    
    public function destroy(int $id): bool;
    
    public function getToSelect(): array;
}
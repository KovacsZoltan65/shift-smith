<?php

declare(strict_types=1);

namespace App\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, \App\Models\Company>
     */
    public function fetch(Request $request): LengthAwarePaginator;
    
    public function getRole(int $id): Role;
    
    public function store(array $data): Role;
    
    public function update(array $data, $id): Role;
    
    public function destroy(int $id): bool;
}


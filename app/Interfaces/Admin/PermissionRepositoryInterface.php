<?php

declare(strict_types=1);

namespace App\Interfaces\Admin;

use App\Models\Admin\Permission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface PermissionRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Permission>
     */
    public function fetch(Request $request): LengthAwarePaginator;

    public function getPermission(int $id): Permission;

    public function getPermissionByName(string $name): Permission;

    /**
     * @param array{name:string, guard_name:string} $data
     */
    public function store(array $data): Permission;

    /**
     * @param array{name:string, guard_name:string} $data
     */
    public function update(array $data, int $id): Permission;

    public function destroyBulk(array $ids): int;
    
    public function destroy(int $id): bool;

    /**
     * @param array{
     *   only_active?: bool
     * } $params
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params = []): array;
}
<?php

declare(strict_types=1);

namespace App\Interfaces\Admin;

use App\Models\Admin\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface RoleRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Role>
     */
    public function fetch(Request $request): LengthAwarePaginator;

    public function getRole(int $id): Role;

    public function getRoleByName(string $name): Role;

    /**
     * @param array{name:string, guard_name:string} $data
     */
    public function store(array $data): Role;

    /**
     * @param array{name:string, guard_name:string} $data
     */
    public function update(array $data, int $id): Role;

    /**
     * @param list<int> $ids
     * @return int
     */
    public function bulkDelete(array $ids): int;
    
    /**
     * @param int $id
     * @return bool
     */
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

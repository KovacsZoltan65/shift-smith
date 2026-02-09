<?php

namespace App\Services\Admin;

use App\Interfaces\Admin\RoleRepositoryInterface;
use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $repo
    ) {}
    
    /**
     * @param Request $request
     * @return LengthAwarePaginator<int, Role>
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Role> $roles */
        $roles = $this->repo->fetch($request);

        return $roles;
    }
    
    /**
     * Summary of getCompany
     * @param int $id
     * @return \App\Models\Role
     */
    public function getRole(int $id): Role
    {
        return $this->repo->getRole($id);
    }
    
    public function getRoleByName(string $name): Role
    {
        return $this->repo->getRoleByName($name);
    }
    
    /**
     * Summary of store
     * @param array{
     *   name: string,
     *   guard_name: string,
     * } $data
     * @return Role
     */
    public function store(array $data): Role
    {
        return $this->repo->store($data);
    }
    
    /**
     * Summary of update
     * @param array{
     *    name: string,
     *    guard_name: string,
     * } $data
     * @param int $id
     * @return Role
     */
    public function update(array $data, $id): Role
    {
        return $this->repo->update($data, $id);
    }
    
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
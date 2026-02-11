<?php

namespace App\Services\Admin;

use App\Interfaces\Admin\PermissionRepositoryInterface;
use App\Models\Admin\Permission;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PermissionService
{
    public function __construct(
        private readonly PermissionRepositoryInterface $repo
    ) {}
    
    /**
     * @param Request $request
     * @return LengthAwarePaginator<int, Permission>
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Permission> $roles */
        $permission = $this->repo->fetch($request);

        return $permission;
    }
    
    /**
     * Summary of getPermission
     * @param int $id
     * @return \App\Models\Permission
     */
    public function getPermission(int $id): Permission
    {
        return $this->repo->getPermission($id);
    }
    
    public function getPermissionByName(string $name): Permission
    {
        return $this->repo->getPermissionByName($name);
    }
    
    /**
     * Summary of store
     * @param array{
     *   name: string,
     *   guard_name: string,
     * } $data
     * @return Permission
     */
    public function store(array $data): Permission
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
     * @return Permission
     */
    public function update(array $data, $id): Permission
    {
        return $this->repo->update($data, $id);
    }
    
    public function destroyBulk(array $ids): int
    {
        // opcionális tisztítás: nullok/duplikátumok kiszűrése
        $ids = array_values(array_unique($ids));
        
        return (int) $this->repo->destroyBulk($ids);
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
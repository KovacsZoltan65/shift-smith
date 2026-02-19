<?php

namespace App\Services\Admin;

use App\Data\Permission\PermissionData;
use App\Interfaces\Admin\PermissionRepositoryInterface;
use App\Models\Admin\Permission;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Jogosultság szolgáltatás osztály
 *
 * Üzleti logikai réteg a jogosultságok (permissions) kezeléséhez.
 * Spatie Permission csomag integrációval.
 * Repository pattern-t használ az adatbázis műveletekhez.
 */
class PermissionService
{
    /**
     * @param PermissionRepositoryInterface $repo Jogosultság repository
     */
    public function __construct(
        private readonly PermissionRepositoryInterface $repo
    ) {}

    /**
     * Jogosultságok listázása lapozással és szűréssel.
     *
     * @param Request $request HTTP kérés (search, field, order, per_page paraméterekkel)
     * @return LengthAwarePaginator<int, Permission> Lapozott jogosultság lista
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Permission> $permissions */
        $permissions = $this->repo->fetch($request);

        return $permissions;
    }

    /**
     * Egy jogosultság lekérése azonosító alapján (policy-barát model lookup).
     *
     * @param int $id Jogosultság azonosító
     * @return Permission Jogosultság model
     */
    public function find(int $id): Permission
    {
        return $this->repo->getPermission($id);
    }

    /**
     * Jogosultság lekérése név alapján (policy-barát model lookup).
     *
     * @param string $name Jogosultság neve
     * @return Permission Jogosultság model
     */
    public function findByName(string $name): Permission
    {
        return $this->repo->getPermissionByName($name);
    }

    /**
     * Egy jogosultság lekérése azonosító alapján.
     *
     * @param int $id Jogosultság azonosító
     * @return Permission Jogosultság model
     */
    public function getPermission(int $id): Permission
    {
        return $this->find($id);
    }

    /**
     * Jogosultság lekérése név alapján.
     *
     * @param string $name Jogosultság neve
     * @return Permission Jogosultság model
     */
    public function getPermissionByName(string $name): Permission
    {
        return $this->findByName($name);
    }

    /**
     * Jogosultság DTO lekérése azonosító alapján.
     *
     * @param int $id Jogosultság azonosító
     * @return PermissionData Jogosultság DTO
     */
    public function getById(int $id): PermissionData
    {
        return PermissionData::fromModel($this->repo->getPermission($id));
    }

    /**
     * Jogosultság DTO lekérése név alapján.
     *
     * @param string $name Jogosultság neve
     * @return PermissionData Jogosultság DTO
     */
    public function getByName(string $name): PermissionData
    {
        return PermissionData::fromModel($this->repo->getPermissionByName($name));
    }

    /**
     * Új jogosultság létrehozása.
     *
     * @param PermissionData $data Jogosultság adatok
     * @return PermissionData Létrehozott jogosultság DTO
     */
    public function store(PermissionData $data): PermissionData
    {
        $permission = $this->repo->store([
            'name' => $data->name,
            'guard_name' => $data->guard_name,
        ]);

        return PermissionData::fromModel($permission);
    }

    /**
     * Jogosultság adatainak frissítése.
     *
     * @param PermissionData $data Frissítendő adatok
     * @param int $id Jogosultság azonosító
     * @return PermissionData Frissített jogosultság DTO
     */
    public function update(PermissionData $data, int $id): PermissionData
    {
        $permission = $this->repo->update([
            'name' => $data->name,
            'guard_name' => $data->guard_name,
        ], $id);

        return PermissionData::fromModel($permission);
    }

    /**
     * Több jogosultság törlése egyszerre.
     *
     * @param list<int> $ids Jogosultság azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    public function bulkDelete(array $ids): int
    {
        $ids = array_values(array_unique($ids));

        return (int) $this->repo->destroyBulk($ids);
    }

    /**
     * Több jogosultság törlése egyszerre (backward compatible alias).
     *
     * @param list<int> $ids Jogosultság azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    public function destroyBulk(array $ids): int
    {
        return $this->bulkDelete($ids);
    }

    /**
     * Egy jogosultság törlése.
     *
     * @param int $id Jogosultság azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }

    /**
     * Jogosultságok lekérése select listához.
     *
     * @return array<int, array{id: int, name: string}> Jogosultságok tömbje
     */
    public function getToSelect(): array
    {
        return $this->repo->getToSelect();
    }
}

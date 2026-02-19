<?php

namespace App\Services\Admin;

use App\Data\Role\RoleData;
use App\Interfaces\Admin\RoleRepositoryInterface;
use App\Models\Admin\Role;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Szerepkör szolgáltatás osztály
 * 
 * Üzleti logikai réteg a szerepkörök (roles) kezeléséhez.
 * Spatie Permission csomag integrációval.
 * Repository pattern-t használ az adatbázis műveletekhez.
 */
class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $repo
    ) {}
    
    /**
     * Szerepkörök listázása lapozással és szűréssel
     * 
     * @param Request $request HTTP kérés (search, field, order, per_page paraméterekkel)
     * @return LengthAwarePaginator<int, Role> Lapozott szerepkör lista
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Role> $roles */
        $roles = $this->repo->fetch($request);

        return $roles;
    }

    /**
     * Egy szerepkör lekérése azonosító alapján (policy-barát model lookup).
     *
     * @param int $id Szerepkör azonosító
     * @return Role Szerepkör model
     */
    public function find(int $id): Role
    {
        return $this->repo->getRole($id);
    }

    /**
     * Szerepkör lekérése név alapján (policy-barát model lookup).
     *
     * @param string $name Szerepkör neve
     * @return Role Szerepkör model
     */
    public function findByName(string $name): Role
    {
        return $this->repo->getRoleByName($name);
    }
    
    /**
     * Egy szerepkör lekérése azonosító alapján
     * 
     * @param int $id Szerepkör azonosító
     * @return Role Szerepkör model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getRole(int $id): Role
    {
        return $this->find($id);
    }
    
    /**
     * Szerepkör lekérése név alapján
     * 
     * @param string $name Szerepkör neve
     * @return Role Szerepkör model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getRoleByName(string $name): Role
    {
        return $this->findByName($name);
    }

    /**
     * Szerepkör DTO lekérése azonosító alapján.
     *
     * @param int $id Szerepkör azonosító
     * @return RoleData Szerepkör DTO
     */
    public function getById(int $id): RoleData
    {
        return RoleData::fromModel($this->repo->getRole($id));
    }

    /**
     * Szerepkör DTO lekérése név alapján.
     *
     * @param string $name Szerepkör neve
     * @return RoleData Szerepkör DTO
     */
    public function getByName(string $name): RoleData
    {
        return RoleData::fromModel($this->repo->getRoleByName($name));
    }
    
    /**
     * Új szerepkör létrehozása
     * 
     * @param RoleData $data Szerepkör adatok
     * @return RoleData Létrehozott szerepkör DTO
     */
    public function store(RoleData $data): RoleData
    {
        $role = $this->repo->store([
            'name' => $data->name,
            'guard_name' => $data->guard_name,
            'permission_ids' => $data->permission_ids,
        ]);

        return RoleData::fromModel($role);
    }
    
    /**
     * Szerepkör adatainak frissítése
     * 
     * @param RoleData $data Frissítendő adatok
     * @param int $id Szerepkör azonosító
     * @return RoleData Frissített szerepkör DTO
     */
    public function update(RoleData $data, int $id): RoleData
    {
        $role = $this->repo->update([
            'name' => $data->name,
            'guard_name' => $data->guard_name,
            'permission_ids' => $data->permission_ids,
        ], $id);

        return RoleData::fromModel($role);
    }
    
    /**
     * Több szerepkör törlése egyszerre
     * 
     * Automatikusan kiszűri a duplikátumokat.
     * 
     * @param list<int> $ids Szerepkör azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    public function bulkDelete(array $ids): int
    {
        // opcionális tisztítás: nullok/duplikátumok kiszűrése
        $ids = array_values(array_unique($ids));
        
        return (int) $this->repo->bulkDelete($ids);
    }
    
    /**
     * Egy szerepkör törlése
     * 
     * @param int $id Szerepkör azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }
    
    /**
     * Szerepkörök lekérése select listához
     * 
     * Egyszerűsített szerepkör lista (id, name) dropdown/select mezőkhöz.
     * 
     * @return array<int, array{id: int, name: string}> Szerepkörök tömbje
     */
    public function getToSelect(): array
    {
        return $this->repo->getToSelect();
    }
}

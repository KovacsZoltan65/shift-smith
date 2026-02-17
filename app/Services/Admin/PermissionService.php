<?php

namespace App\Services\Admin;

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
    public function __construct(
        private readonly PermissionRepositoryInterface $repo
    ) {}
    
    /**
     * Jogosultságok listázása lapozással és szűréssel
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
     * Egy jogosultság lekérése azonosító alapján
     * 
     * @param int $id Jogosultság azonosító
     * @return Permission Jogosultság model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getPermission(int $id): Permission
    {
        return $this->repo->getPermission($id);
    }
    
    /**
     * Jogosultság lekérése név alapján
     * 
     * @param string $name Jogosultság neve
     * @return Permission Jogosultság model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getPermissionByName(string $name): Permission
    {
        return $this->repo->getPermissionByName($name);
    }
    
    /**
     * Új jogosultság létrehozása
     * 
     * @param array{
     *   name: string,
     *   guard_name: string,
     * } $data Jogosultság adatok
     * @return Permission Létrehozott jogosultság
     */
    public function store(array $data): Permission
    {
        return $this->repo->store($data);
    }
    
    /**
     * Jogosultság adatainak frissítése
     * 
     * @param array{
     *    name: string,
     *    guard_name: string,
     * } $data Frissítendő adatok
     * @param int $id Jogosultság azonosító
     * @return Permission Frissített jogosultság
     */
    public function update(array $data, $id): Permission
    {
        return $this->repo->update($data, $id);
    }
    
    /**
     * Több jogosultság törlése egyszerre
     * 
     * Automatikusan kiszűri a duplikátumokat.
     * 
     * @param list<int> $ids Jogosultság azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    public function destroyBulk(array $ids): int
    {
        // opcionális tisztítás: nullok/duplikátumok kiszűrése
        $ids = array_values(array_unique($ids));
        
        return (int) $this->repo->destroyBulk($ids);
    }
    
    /**
     * Egy jogosultság törlése
     * 
     * @param int $id Jogosultság azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }
    
    /**
     * Jogosultságok lekérése select listához
     * 
     * Egyszerűsített jogosultság lista (id, name) dropdown/select mezőkhöz.
     * 
     * @return array<int, array{id: int, name: string}> Jogosultságok tömbje
     */
    public function getToSelect(): array
    {
        return $this->repo->getToSelect();
    }
}
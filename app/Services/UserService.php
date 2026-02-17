<?php

namespace App\Services;

use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Felhasználó szolgáltatás osztály
 * 
 * Üzleti logikai réteg a felhasználók kezeléséhez.
 * Repository pattern-t használ az adatbázis műveletekhez.
 */
class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $repo
    ) {}

    /**
     * Felhasználók listázása lapozással és szűréssel
     * 
     * @param Request $request HTTP kérés (search, field, order, per_page paraméterekkel)
     * @return LengthAwarePaginator<int, User> Lapozott felhasználó lista
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }
    
    /**
     * Egy felhasználó lekérése azonosító alapján
     *
     * @param int $id Felhasználó azonosító
     * @return User Felhasználó model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getUser(int $id): User
    {
        return $this->repo->getUser($id);
    }
    
    /**
     * Felhasználó lekérése név alapján
     * 
     * @param string $name Felhasználó neve
     * @return User Felhasználó model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getUserByName(string $name): User
    {
        return $this->repo->getUserByName($name);
    }
    
    /**
     * Új felhasználó létrehozása
     * 
     * Létrehozza a felhasználót és jelszó reset linket küld neki.
     * 
     * @param array{
     *   name: string,
     *   email: string,
     *   password: string,
     *   roles?: array<int, string>
     * } $data Felhasználó adatok
     * @return User Létrehozott felhasználó
     */
    public function store(array $data): User
    {
        return $this->repo->store($data);
    }
    
    /**
     * Felhasználó adatainak frissítése
     * 
     * @param Request $request HTTP kérés a frissítendő adatokkal
     * @param int $id Felhasználó azonosító
     * @return User Frissített felhasználó
     */
    public function update(Request $request, int $id): User
    {
        /** @var array{
         *   name: string,
         *   email: string,
         *   password: string,
         *   company_id?: int|null,
         *   is_active?: bool
         * } $data
         */
        $data = $request->all();
        return $this->repo->update($data, $id);
    }
    
    /**
     * Több felhasználó törlése egyszerre
     * 
     * Automatikusan kiszűri a duplikátumokat.
     * 
     * @param list<int> $ids Felhasználó azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    public function bulkDelete(array $ids): int
    {
        // opcionális tisztítás: nullok/duplikátumok kiszűrése
        $ids = array_values(array_unique($ids));
        
        return (int) $this->repo->bulkDelete($ids);
    }
    
    /**
     * Egy felhasználó törlése
     * 
     * @param int $id Felhasználó azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }
}

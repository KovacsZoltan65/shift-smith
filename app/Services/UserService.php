<?php

namespace App\Services;

use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $repo
    ) {}

    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }
    
    /**
     * Egy felhasználó lekérése azonosító alapján.
     *
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található.
     */
    public function getUser(int $id): User
    {
        return $this->repo->getUser($id);
    }
    
    /**
     * Rekord lekérése név alapján
     * 
     * @param string $name
     * @return User
     */
    public function getUserByName(string $name): User
    {
        return $this->repo->getUserByName($name);
    }
    
    /**
     * Új felhasználó mentése
     * 
     * @param Request $request
     * @return User
     */
    public function store(Request $request): User
    {
        return $this->repo->store($request);
    }
    
    /**
     * Felhasználó adatainak mentése
     * 
     * @param Request $request
     * @param int $id
     * @return User
     */
    public function update(Request $request, int $id): User
    {
        return $this->repo->update($request->all(), $id);
    }
    
    public function bulkDelete(array $ids): int
    {
        // opcionális tisztítás: nullok/duplikátumok kiszűrése
        $ids = array_values(array_unique(array_filter($ids, static fn ($v) => $v !== null)));
        
        return (int) $this->repo->bulkDelete($ids);
    }
    
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }
}

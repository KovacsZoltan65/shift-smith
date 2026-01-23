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

    /**
     * @return LengthAwarePaginator<int, User>
     */
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
     * @param array{
     *   name: string,
     *   email: string,
     *   password: string,
     *   roles?: array<int, string>
     * } $data
     * @return User
     */
    public function store(array $data): User
    {
        return $this->repo->store($data);
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
     * @param list<int> $ids
     * @return int A törölt rekordok száma
     */
    public function bulkDelete(array $ids): int
    {
        // opcionális tisztítás: nullok/duplikátumok kiszűrése
        $ids = array_values(array_unique($ids));
        
        return (int) $this->repo->bulkDelete($ids);
    }
    
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }
}

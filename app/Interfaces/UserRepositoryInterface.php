<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface UserRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, \App\Models\User>
     */
    public function fetch(Request $request): LengthAwarePaginator;

    /**
     * @param array{
     *   search?: string|null
     * } $params
     * @return array<int, array{id:int, name:string, email:string}>
     */
    public function getToSelect(array $params = []): array;

    public function getUser(int $id): User;

    public function getUserByName(string $name): User;

    /**
     * Új felhasználó mentése
     * 
      * @param array{
      *   name: string,
      *   email: string,
      *   password: string,
      *   company_id?: int|null,
      *   is_active?: bool,
      * } $data
     * @return User
     */
    public function store(array $data): User;

    /**
     * Felhasználó adatainak mentése
     * 
     * @param array{
     *   name: string,
     *   email: string,
     *   password: string,
     *   company_id?: int|null,
     *   is_active?: bool,
     * } $data
     * @param int $id
     * @return User
     */
    public function update(array $data, int $id): User;

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids): int;

    public function destroy(int $id): bool;
}

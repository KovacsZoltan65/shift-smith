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

    public function getUser(int $id): User;

    public function getUserByName(string $name): User;

    public function store(array $data): User;

    public function update(array $data, int $id): User;

    public function bulkDelete(array $ids): int;

    public function destroy(int $id): bool;
}

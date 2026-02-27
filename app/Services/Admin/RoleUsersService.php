<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin\Role;
use App\Repositories\Admin\RoleUsersRepository;

final class RoleUsersService
{
    public function __construct(
        private readonly RoleUsersRepository $repository,
    ) {}

    /**
     * @param list<int> $userIds
     */
    public function syncUsers(Role $role, array $userIds): Role
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        return $this->repository->syncUsers($role, $userIds);
    }
}

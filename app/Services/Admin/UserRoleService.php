<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use App\Repositories\Admin\UserRoleRepository;
use App\Repositories\Admin\RoleRepository;

final class UserRoleService
{
    public function __construct(
        private readonly UserRoleRepository $repository,
        private readonly RoleRepository $roleRepository,
    ) {}

    public function setPrimaryRole(User $user, int $roleId): User
    {
        $role = $this->roleRepository->getRole($roleId);

        return $this->repository->setPrimaryRole($user, $role);
    }
}

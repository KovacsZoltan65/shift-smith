<?php

declare(strict_types=1);

namespace App\Support;

final class PermissionCatalog
{
    /**
     * @return array{
     *   users:list<string>,
     *   roles:list<string>,
     *   user_assignments:list<string>
     * }
     */
    public static function p0(): array
    {
        return [
            'users' => [
                'users.view',
                'users.viewAny',
                'users.create',
                'users.update',
                'users.delete',
                'users.deleteAny',
                'users.assignRoles',
            ],
            'roles' => [
                'roles.view',
                'roles.viewAny',
                'roles.create',
                'roles.update',
                'roles.delete',
                'roles.deleteAny',
            ],
            'user_assignments' => [
                'user_assignments.viewAny',
                'user_assignments.update',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function p0Flat(): array
    {
        return array_values(array_merge(...array_values(self::p0())));
    }
}

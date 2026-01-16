<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MenuPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // cache reset (különben "nem látja" az új jogokat)
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        $permissions = [
            // Adminisztráció
            'users.viewAny',
            'companies.viewAny',

            // Biztonság
            'permissions.viewAny',
            'roles.viewAny',

            // HR
            'employees.viewAny',
            'assignments.viewAny',
            'shifts.viewAny',
            'planning.view',

            // Beállítások
            'settings.app',
            'settings.company',
            'settings.user',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => $guard,
            ]);
        }

        // superadmin role (ha nincs, létrehozza)
        $superadmin = Role::query()->firstOrCreate([
            'name' => 'superadmin',
            'guard_name' => $guard,
        ]);

        // MINDEN jogot kapjon (biztos ami biztos)
        $superadmin->syncPermissions(Permission::query()->where('guard_name', $guard)->get());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

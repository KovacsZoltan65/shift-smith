<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Roles
        $superadmin = Role::firstOrCreate(['name' => 'superadmin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'manager']);
        Role::firstOrCreate(['name' => 'user']);

        // ---------------------------------------------
        // SUPERADMIN
        // ---------------------------------------------
        
        // MVP permissions (később bővítjük modulonként)
        $permissions = [
            'schedule.viewAny',
            'schedule.create',
            'schedule.update',
            'schedule.delete',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        // superadmin kap mindent
        $superadmin->syncPermissions(Permission::all());
    }
}

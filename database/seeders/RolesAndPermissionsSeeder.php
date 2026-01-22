<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Employee;
use App\Models\Company;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Activity log kikapcsolása seedelés idejére,
        // hogy ne szemeteljük tele a logot automatikus műveletekkel
        activity()->disableLogging();

        // Spatie Permission cache ürítése,
        // különben régi jogosultságok maradhatnak memóriában
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Konzol üzenet: seeder indul
        $this->command->info('🔐 PermissionSeeder: jogosultságok és szerepkörök seedelése indul...');

        // Entitások, amelyekhez jogosultságokat generálunk
        // kulcs: permission prefix, érték: model osztály
        $entities = [
            'users'     => User::class,
            'employees' => Employee::class,
            'companies' => Company::class,
        ];

        // Alap CRUD + force delete jogosultságok
        // Ezek minden entitásra egységesen érvényesek
        $baseActions = [
            'view', 'viewAny',
            'create', 'update',
            'delete', 'deleteAny',
            'forceDelete', 'forceDeleteAny',
        ];

        // SoftDeletes-t használó modellek extra action-jei
        $softDeleteExtras = ['restore', 'restoreAny'];

        // Progress bar becsült lépésszám:
        // permission darabszám + (4 role létrehozás + 4 role sync)
        $estimated = 0;

        // Előszámoljuk, hány permission fog létrejönni
        foreach ($entities as $entity => $modelClass) {
            $actions = $baseActions;

            // Ha a modell használja a SoftDeletes traitet,
            // hozzáadjuk a restore jogosultságokat
            if (\in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                $actions = [
                    ...$actions,
                    ...$softDeleteExtras,
                ];
            }

            // User entitás extra jogosultsága:
            // szerepkörök kiosztása
            if ($entity === 'users') {
                $actions[] = 'assignRoles';
            }

            // Egyedi action-ök számolása
            $estimated += \count(array_unique($actions));
        }

        // Role-ok: 4 create + 4 sync
        $estimated += 8;

        // Progress bar inicializálása
        $bar = $this->command->getOutput()->createProgressBar($estimated);
        $bar->start();

        // Teljes permission + role seedelés egy DB tranzakcióban,
        // hogy félbehagyás esetén ne maradjon inkonzisztens állapot
        \DB::transaction(function () use ($entities, $baseActions, $softDeleteExtras, $bar): void {

            // Permission-ök létrehozása entitásonként
            foreach ($entities as $entity => $modelClass) {
                $actions = $baseActions;

                // SoftDeletes extra action-ök
                if (\in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                    $actions = [
                        ...$actions,
                        ...$softDeleteExtras,
                    ];
                }

                // User-specifikus jogosultság
                if ($entity === 'users') {
                    $actions[] = 'assignRoles';
                }

                // Permission rekordok létrehozása (idempotens módon)
                foreach (array_unique($actions) as $action) {
                    Permission::firstOrCreate([
                        'name' => "{$entity}.{$action}",
                    ]);
                    $bar->advance();
                }
            }

            // Alap szerepkörök létrehozása
            $superAdminRole = Role::firstOrCreate(['name' => 'superadmin']); $bar->advance();
            $adminRole      = Role::firstOrCreate(['name' => 'admin']);      $bar->advance();
            $operatorRole   = Role::firstOrCreate(['name' => 'operator']);   $bar->advance();
            $userRole       = Role::firstOrCreate(['name' => 'user']);       $bar->advance();

            // Superadmin: minden jogosultság megkap
            $superAdminRole->syncPermissions(Permission::all());
            $bar->advance();

            // Admin: minden, kivéve force delete jogosultságok
            $adminRole->syncPermissions(
                Permission::where('name', 'not like', '%.forceDelete%')->get()
            );
            $bar->advance();

            // Operator: csak listázás és megtekintés
            $operatorRole->syncPermissions(
                Permission::where(function ($q) {
                    $q->where('name', 'like', '%.viewAny')
                    ->orWhere('name', 'like', '%.view');
                })->get()
            );
            $bar->advance();

            // User: csak egyedi megtekintés
            $userRole->syncPermissions(
                Permission::where('name', 'like', '%.view')->get()
            );
            $bar->advance();
        });

        // Progress bar lezárása
        $bar->finish();
        $this->command->newLine(2);

        // Seeder kész
        $this->command->info('✅ PermissionSeeder kész.');

        // Permission cache újraürítése, hogy az új adatok azonnal éljenek
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Activity log visszakapcsolása
        activity()->enableLogging();
    }

}

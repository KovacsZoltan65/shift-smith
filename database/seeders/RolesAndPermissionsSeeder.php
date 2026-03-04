<?php

namespace Database\Seeders;

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkPattern;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Support\PermissionCatalog;
use App\Support\MenuPermissions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Fő belépési pont.
     *
     * Feladata:
     *  - CRUD alapú permissionök generálása entitásonként
     *  - alap szerepkörök létrehozása
     *  - szerepkörök jogosultságainak kiosztása
     *  - menü által használt permission stringek auditálása
     */
    public function run(): void
    {
        /**
         * Activity log kikapcsolása seedelés idejére.
         * Így nem generálunk felesleges audit bejegyzéseket.
         */
        activity()->disableLogging();

        /**
         * Spatie permission cache ürítése.
         * Ha ezt nem tesszük meg, akkor a memóriában
         * régi jogosultság lista maradhat.
         */
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('🔐 PermissionSeeder: jogosultságok és szerepkörök seedelése indul...');

        /**
         * Entitások, amelyekhez jogosultságokat generálunk.
         *
         * Kulcs = permission prefix
         * Érték = model osztály
         *
         * FONTOS:
         * A menüben használt "can" stringeknek
         * ezekkel a prefixekkel kell kezdődniük.
         */
        $entities = [
            //'app_settings' => AppSettings::class,
            'users'          => User::class,
            'employees'      => Employee::class,
            'positions'      => Position::class,
            'companies'      => Company::class,
            'roles'          => Role::class,
            'permissions'    => Permission::class,
            'work_shifts'    => WorkShift::class,
            'work_schedules' => WorkSchedule::class,
            'work_schedule_assignments' => \App\Models\WorkShiftAssignment::class,
            'work_patterns'  => WorkPattern::class,
            'employee_work_patterns' => EmployeeWorkPattern::class,
        ];

        /** @var list<string> $customPermissions */
        $customPermissions = [
            'app_settings.view',
            'app_settings.viewAny',
            'app_settings.create',
            'app_settings.update',
            'app_settings.delete',
            'app_settings.deleteAny',
            'company_settings.view',
            'company_settings.viewAny',
            'company_settings.create',
            'company_settings.update',
            'company_settings.delete',
            'company_settings.deleteAny',
            'leave_categories.viewAny',
            'leave_categories.create',
            'leave_categories.update',
            'leave_categories.delete',
            'leave_types.view',
            'leave_types.viewAny',
            'leave_types.create',
            'leave_types.update',
            'leave_types.delete',
            'sick_leave_categories.viewAny',
            'sick_leave_categories.create',
            'sick_leave_categories.update',
            'sick_leave_categories.delete',
            'absences.viewAny',
            'absences.create',
            'absences.update',
            'absences.delete',
            'user_settings.view',
            'user_settings.viewAny',
            'user_settings.create',
            'user_settings.update',
            'user_settings.delete',
            'user_settings.deleteAny',
            'user_settings.manageOthers',
            'hq.companies.view',
            'employee_work_patterns.assign',
            'employee_work_patterns.unassign',
            'employee_work_patterns.view',
            'settings.viewApp',
            'settings.updateApp',
            'settings.viewCompany',
            'settings.updateCompany',
            'settings.viewUser',
            'settings.updateUser',
            'user_employees.viewAny',
            'user_employees.create',
            'user_employees.delete',
            'user_assignments.viewAny',
            'user_assignments.update',
            'month_closures.viewAny',
            'month_closures.close',
            'month_closures.reopen',
        ];

        /**
         * Alap CRUD + force delete jogosultságok.
         * Ezek minden entitásra egységesen érvényesek.
         */
        $baseActions = [
            'view', 'viewAny',
            'create', 'update',
            'delete', 'deleteAny',
            'forceDelete', 'forceDeleteAny',
        ];

        /**
         * SoftDeletes-t használó modellek extra action-jei.
         */
        $softDeleteExtras = ['restore', 'restoreAny'];

        /**
         * Progress bar lépésszám becslése.
         * (permission darabszám + 4 role létrehozás + 4 role sync)
         */
        $estimated = 0;

        foreach ($entities as $entity => $modelClass) {
            $actions = $baseActions;

            // Ha a modell használja a SoftDeletes traitet,
            // hozzáadjuk a restore jogokat.
            if (\in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                $actions = [...$actions, ...$softDeleteExtras];
            }

            // User-specifikus extra jog
            if ($entity === 'users') {
                $actions[] = 'assignRoles';
            }

            $estimated += \count(array_unique($actions));
        }

        // 4 role create + 4 sync
        $estimated += \count($customPermissions);
        $estimated += 8;

        $bar = $this->command->getOutput()->createProgressBar($estimated);
        $bar->start();

        /**
         * Teljes permission + role seedelés tranzakcióban.
         * Így félbeszakadás esetén nem marad inkonzisztens állapot.
         */
        \DB::transaction(function () use ($entities, $baseActions, $softDeleteExtras, $customPermissions, $bar): void {

            /**
             * Permission generálás entitásonként.
             */
            foreach ($entities as $entity => $modelClass) {
                $actions = $baseActions;

                if (\in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                    $actions = [...$actions, ...$softDeleteExtras];
                }

                if ($entity === 'users') {
                    $actions[] = 'assignRoles';
                }

                // Idempotens létrehozás
                foreach (array_unique($actions) as $action) {
                    Permission::firstOrCreate([
                        'name' => "{$entity}.{$action}",
                    ]);
                    $bar->advance();
                }
            }

            foreach ($customPermissions as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName]);
                $bar->advance();
            }

            foreach (PermissionCatalog::p0Flat() as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName]);
            }

            /**
             * Alap szerepkörök létrehozása.
             */
            $superAdminRole = Role::firstOrCreate(['name' => 'superadmin']); $bar->advance();
            $adminRole      = Role::firstOrCreate(['name' => 'admin']);      $bar->advance();
            $operatorRole   = Role::firstOrCreate(['name' => 'operator']);   $bar->advance();
            $userRole       = Role::firstOrCreate(['name' => 'user']);       $bar->advance();

            /**
             * Jogosultság kiosztási stratégia:
             *
             * superadmin → minden jog
             * admin      → minden, kivéve forceDelete
             * operator   → csak view és viewAny
             * user       → csak view
             */

            $superAdminRole->syncPermissions(Permission::all());
            $bar->advance();

            $adminRole->syncPermissions(
                Permission::where('name', 'not like', '%.forceDelete%')
                    ->where('name', 'not like', 'hq.%')
                    ->get()
            );
            $bar->advance();

            $operatorRole->syncPermissions(
                Permission::where(function ($q) {
                    $q->where('name', 'like', '%.viewAny')
                      ->orWhere('name', 'like', '%.view');
                })->where('name', 'not like', 'hq.%')->get()
            );
            $bar->advance();

            $userRole->syncPermissions(
                Permission::where('name', 'like', '%.view')
                    ->where('name', 'not like', 'hq.%')
                    ->get()
            );
            $bar->advance();
        });

        $bar->finish();
        $this->command->newLine(2);

        /**
         * Menü permission audit.
         *
         * Ellenőrzi, hogy a menüben használt
         * "can: 'xxx.yyy'" stringek léteznek-e.
         *
         * Nem generál új jogokat,
         * csak figyelmeztet, ha eltérés van.
         */
        $this->auditMenuPermissions();

        /**
         * Cache újraürítése,
         * hogy az új permission lista azonnal éljen.
         */
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /**
         * Activity log visszakapcsolása.
         */
        activity()->enableLogging();

        $this->command->info('✅ PermissionSeeder kész.');
    }

    /**
     * Menü permission audit.
     *
     * Kigyűjti a menü definícióból a can stringeket,
     * és ellenőrzi, hogy léteznek-e a permission táblában.
     */
    private function auditMenuPermissions(): void
    {
        $menuPerms = MenuPermissions::collect();

        if (empty($menuPerms)) {
            return;
        }

        $missing = [];

        foreach ($menuPerms as $permissionName) {
            if (!Permission::where('name', $permissionName)->exists()) {
                $missing[] = $permissionName;
            }
        }

        if (!empty($missing)) {
            $this->command->warn('⚠️ Menü permission hiányzik:');

            foreach ($missing as $p) {
                $this->command->warn(" - {$p}");
            }

            /**
             * Ha szigorú módot akarsz:
             *
             * throw new \RuntimeException(
             *     'Missing menu permissions: ' . implode(', ', $missing)
             * );
             */
        }
    }
}

<?php

namespace Database\Seeders;

use Database\Seeders\Pivot\PivotSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsMetaSeeder::class,
            AppSettingsSeeder::class,
            
            SuperAdminSeeder::class,
            AdminSeeder::class,
            OperatorSeeder::class,
            UserSeeder::class,

            CompanySeeder::class,
            PositionSeeder::class,
            PositionOrgLevelsSeeder::class,
            EmployeeSeeder::class,
            PivotSeeder::class,
            
            WorkShiftSeeder::class,
            WorkShiftAssignmentSeeder::class,
            WorkPatternSeeder::class,

            LeaveCategoriesSeeder::class,
            SickLeaveCategoriesSeeder::class,
            LeaveCarryOverAppSettingsSeeder::class,
            LeaveTypesSeeder::class,

            OrgHierarchySeeder::class,
        ]);

        if (filter_var((string) env('ORG_SEEDER_ENABLED', 'false'), FILTER_VALIDATE_BOOL)) {
            $this->call([
                OrgHierarchySeeder::class,
            ]);
        }

        // Demo tenant explicit, opt-in seed:
        // $this->call(\Database\Seeders\Demo\DemoTenantSeeder::class);
        // User::factory(10)->create();

        //User::factory()->create([
        //    'name' => 'Test User',
        //    'email' => 'test@example.com',
        //]);
    }
}

<?php

namespace Database\Seeders;

use Database\Seeders\Pivot\CompanyEmployeeSeeder;
use Database\Seeders\Pivot\PivotSeeder;
use App\Models\User;
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
            EmployeeSeeder::class,
            PivotSeeder::class,
            
            WorkShiftSeeder::class,
            WorkShiftAssignmentSeeder::class,
            WorkPatternSeeder::class,
        ]);
        // User::factory(10)->create();

        //User::factory()->create([
        //    'name' => 'Test User',
        //    'email' => 'test@example.com',
        //]);
    }
}

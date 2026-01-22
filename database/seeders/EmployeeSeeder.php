<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        activity()->disableLogging();

        $count = 25;

        $this->command->info("🔄 Employees létrehozása... ($count db)");

        Employee::factory($count)->create();

        $this->command->info("✅ {$count} alkalmazott sikeresen létrehozva.");

        activity()->enableLogging();
    }
}
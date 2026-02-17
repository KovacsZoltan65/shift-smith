<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class WorkScheduleSeeder extends Seeder
{
    public function run(): void
    {
        if (! Company::exists()) {
            $this->command->warn('⚠️ Nincs Company rekord, WorkSchedule seeding kihagyva.');
            return;
        }

        Company::query()->each(function (Company $company): void {
            WorkSchedule::factory()
                ->count(rand(3, 6))
                ->state(['company_id' => $company->id])
                ->create();
        });
    }
}

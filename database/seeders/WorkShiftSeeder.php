<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\Company;
use App\Models\WorkShift;
use Carbon\Carbon;

class WorkShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Biztonsági ellenőrzés
        if (!Company::exists()) {
            $this->command?->warn('⚠️ Nincs Company rekord, WorkShift seeding kihagyva.');
            return;
        }
        
        // Cégenként generálunk műszakokat
        Company::query()->each(function (Company $company) {
            WorkShift::factory()
                ->count(rand(3, 10)) // cégenként 3–10 műszak
                ->state([
                    'company_id' => $company->id,
                ])
                ->create();
        });
    }
}

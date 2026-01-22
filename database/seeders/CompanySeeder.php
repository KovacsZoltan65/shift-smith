<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // Külső kulcsok tiltása, tábla ürítése
        //Schema::disableForeignKeyConstraints();
        //Company::query()->truncate();
        //Schema::enableForeignKeyConstraints();

        // Logolás letiltása (Spatie Activitylog)
        activity()->disableLogging();

        $count = 100;

        $this->command->warn("Creating {$count} companies...");
        $this->command->getOutput()->progressStart($count);

        for ($i = 0; $i < $count; $i++) {
            // Generáljunk egy adatot a factoryból, de nem mentjük el,
            // csak értékeket kérünk
            $attributes = Company::factory()->make()->toArray();

            // A keresési feltételhez pl. az emailt használjuk,
            // hogy ne legyen duplikáció - de lehet akár a name is
            $company = Company::firstOrCreate(
                ['email' => $attributes['email']],  // Keresési feltétel
                $attributes                         // Ha nem találja,
                // létrehozza ez alapján
            );

            // Haladás jelzése
            $this->command->getOutput()->progressAdvance();
        }

        $this->command->getOutput()->progressFinish();
        $this->command->info("{$count} companies created.");

        activity()->enableLogging();
    }
}
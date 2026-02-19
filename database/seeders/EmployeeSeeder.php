<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dolgozó adatok seedelése cégenként.
 *
 * Minden céghez fix számú (jelenleg 50) dolgozót hoz létre
 * gyors tömeges INSERT használatával.
 */
class EmployeeSeeder extends Seeder
{
    /**
     * Seeder futtatása.
     *
     * @return void
     */
    public function run(): void
    {
        activity()->disableLogging();

        $countPerCompany = 50;
        $companyIds = Company::query()->pluck('id');

        if ($companyIds->isEmpty()) {
            $this->command->warn('Nincs cég, ezért dolgozó seeding kihagyva.');
            activity()->enableLogging();
            return;
        }

        $this->command->info("Employees létrehozása cégenként... ({$countPerCompany} fő/cég)");

        $companyIds->each(function (int $companyId) use ($countPerCompany): void {
            $rows = [];
            $now = now();

            for ($i = 1; $i <= $countPerCompany; $i++) {
                $firstName = fake()->firstName();
                $lastName = fake()->lastName();

                $rows[] = [
                    'company_id' => $companyId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => sprintf('employee_%d_%d_%s@example.com', $companyId, $i, Str::lower(Str::random(8))),
                    'address' => fake()->address(),
                    'position' => fake()->jobTitle(),
                    'phone' => fake()->phoneNumber(),
                    'hired_at' => fake()->date(),
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('employees')->insert($rows);
        });

        $total = $companyIds->count() * $countPerCompany;
        $this->command->info("{$total} alkalmazott sikeresen létrehozva ({$countPerCompany} db/cég).");

        activity()->enableLogging();
    }
}

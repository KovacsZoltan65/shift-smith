<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\Position;
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
            $positionIds = Position::query()
                ->where('company_id', $companyId)
                ->pluck('id')
                ->all();

            if (empty($positionIds)) {
                return;
            }

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
                    'position_id' => fake()->randomElement($positionIds),
                    'phone' => fake()->phoneNumber(),
                    'hired_at' => fake()->date(),
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('employees')->insert($rows);

            Employee::query()
                ->where('company_id', $companyId)
                ->whereDoesntHave('profile')
                ->get(['id', 'company_id'])
                ->each(function (Employee $employee): void {
                    EmployeeProfile::factory()->create([
                        'company_id' => (int) $employee->company_id,
                        'employee_id' => (int) $employee->id,
                    ]);
                });
        });

        $total = $companyIds->count() * $countPerCompany;
        $this->command->info("{$total} alkalmazott sikeresen létrehozva ({$countPerCompany} db/cég).");

        activity()->enableLogging();
    }
}

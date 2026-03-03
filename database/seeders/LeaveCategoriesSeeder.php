<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class LeaveCategoriesSeeder extends Seeder
{
    /**
     * @return list<array{code:string,name:string,description:string,order_index:int}>
     */
    public static function defaults(): array
    {
        return [
            [
                'code' => 'leave',
                'name' => 'Szabadsag',
                'description' => 'Az altalanos szabadsag jellegu tavolletek gyujtokategoriaja.',
                'order_index' => 10,
            ],
            [
                'code' => 'sick_leave',
                'name' => 'Betegszabadsag',
                'description' => 'Betegseghez vagy keresokeptelenseghez kapcsolodo tavolletek kategoriája.',
                'order_index' => 20,
            ],
            [
                'code' => 'paid_absence',
                'name' => 'Fizetett tavollet',
                'description' => 'Olyan tavolletek gyujtokategoriaja, amelyek nem csokkentik a szabadsagkeretet.',
                'order_index' => 30,
            ],
            [
                'code' => 'unpaid_absence',
                'name' => 'Fizetes nelkuli tavollet',
                'description' => 'Minden fizetes nelkuli, kulon kezelt tavollet gyujtokategoriaja.',
                'order_index' => 40,
            ],
        ];
    }

    public function run(): void
    {
        if (! Company::query()->exists()) {
            $this->command?->warn('Nincs cég, ezért LeaveCategories seeding kihagyva.');
            return;
        }

        $items = self::defaults();

        Company::query()
            ->select(['id'])
            ->chunkById(100, function ($companies) use ($items): void {
                foreach ($companies as $company) {
                    foreach ($items as $item) {
                        DB::table('leave_categories')->updateOrInsert(
                            [
                                'company_id' => (int) $company->id,
                                'code' => $item['code'],
                            ],
                            [
                                'name' => $item['name'],
                                'description' => $item['description'],
                                'active' => true,
                                'order_index' => $item['order_index'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            });
    }
}

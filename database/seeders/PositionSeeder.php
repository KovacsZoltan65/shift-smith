<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        if (!Company::exists()) {
            $this->command->warn('Nincs cég, ezért Position seeding kihagyva.');
            return;
        }

        $items = [
            ['name' => 'Gépkezelő', 'description' => null, 'active' => true],
            ['name' => 'Osztályvezető', 'description' => null, 'active' => true],
            ['name' => 'Rakodó', 'description' => null, 'active' => true],
            ['name' => 'Sofőr', 'description' => null, 'active' => true]
        ];

        Company::query()->each(function (Company $company) use ($items): void {
            foreach ($items as $item) {
                Position::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'name' => $item['name'],
                    ],
                    [
                        'description' => $item['description'],
                        'active' => $item['active'],
                    ]
                );
            }
        });
    }
}

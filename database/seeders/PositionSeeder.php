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
            ['name' => 'Operátor', 'description' => 'Termelési operátor', 'active' => true],
            ['name' => 'Műszakvezető', 'description' => 'Műszak csapatvezető', 'active' => true],
            ['name' => 'Raktáros', 'description' => 'Raktári munkakör', 'active' => true],
            ['name' => 'HR generalista', 'description' => 'HR támogató szerep', 'active' => true],
            ['name' => 'Karbantartó', 'description' => 'Műszaki karbantartás', 'active' => true],
            ['name' => 'Minőségellenőr', 'description' => 'QA ellenőrzés', 'active' => true],
            ['name' => 'Irodai ügyintéző', 'description' => 'Adminisztráció', 'active' => true],
            ['name' => 'Termelési koordinátor', 'description' => 'Termelés koordinálása', 'active' => true],
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

<?php

declare(strict_types=1);

namespace Database\Seeders\Pivot;

use Illuminate\Database\Seeder;

class PivotSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanyEmployeeSeeder::class,
            UserEmployeeSeeder::class,
        ]);
    }
}


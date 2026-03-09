<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TenantGroup;
use Illuminate\Database\Seeder;

final class TenantGroupSeeder extends Seeder
{
    public function run(): void
    {
        TenantGroup::factory()->count(5)->create();
    }
}

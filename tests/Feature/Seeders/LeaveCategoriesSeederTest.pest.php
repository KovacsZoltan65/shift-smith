<?php

declare(strict_types=1);

use Database\Seeders\LeaveCategoriesSeeder;

it('company-nkent letrehozza a leave category seed lista elemeit', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();

    $this->seed(LeaveCategoriesSeeder::class);

    foreach (LeaveCategoriesSeeder::defaults() as $item) {
        $this->assertDatabaseHas('leave_categories', [
            'company_id' => $company->id,
            'code' => $item['code'],
            'name' => $item['name'],
        ]);
    }
});

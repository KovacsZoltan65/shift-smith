<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkPattern;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('nem engedi más tenant munkarendjének lekérését', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $pattern = WorkPattern::factory()->create(['company_id' => $companyA->id]);

    $this->actingAs($user)
        ->getJson(route('work_patterns.by_id', [
            'id' => $pattern->id,
            'company_id' => $companyB->id,
        ]))
        ->assertNotFound();
});

it('nem engedi más tenant munkarendjének törlését', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $pattern = WorkPattern::factory()->create(['company_id' => $companyA->id]);

    $this->actingAs($user)
        ->deleteJson(route('work_patterns.destroy', ['id' => $pattern->id]), [
            'company_id' => $companyB->id,
        ])
        ->assertNotFound();
});

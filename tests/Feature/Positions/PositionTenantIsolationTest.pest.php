<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Position;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('nem enged cégidegen pozíció módosítást', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $position = Position::factory()->create([
        'company_id' => $companyA->id,
        'name' => 'A pozíció',
    ]);

    $this->actingAs($user)
        ->putJson(route('positions.update', $position->id), [
            'company_id' => $companyB->id,
            'name' => 'Tiltott',
            'description' => null,
            'active' => true,
        ])
        ->assertNotFound();
});

it('nem enged cégidegen pozíció törlést', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $position = Position::factory()->create(['company_id' => $companyA->id]);

    $this->actingAs($user)
        ->deleteJson(route('positions.destroy', $position->id), [
            'company_id' => $companyB->id,
        ])
        ->assertNotFound();
});

<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Position;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('visszaadja a pozíció selector listát', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $company = Company::factory()->create();

    Position::factory()->create(['company_id' => $company->id, 'name' => 'Operátor']);

    $this->actingAs($user)
        ->getJson(route('selectors.positions', ['company_id' => $company->id]))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Operátor']);
});

it('tömegesen törli a kijelölt pozíciókat', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $company = Company::factory()->create();

    $ids = Position::factory()->count(2)->create(['company_id' => $company->id])->pluck('id')->all();

    $this->actingAs($user)
        ->deleteJson(route('positions.destroy_bulk'), ['company_id' => $company->id, 'ids' => $ids])
        ->assertOk()
        ->assertJsonPath('deleted', 2);
});

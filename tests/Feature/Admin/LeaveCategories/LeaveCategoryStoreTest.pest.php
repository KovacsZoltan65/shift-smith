<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('code nelkul is letrehozza a rekordot es general egy company-scope egyedi kodot', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.leave_categories.store'), [
            'name' => 'Rendkivuli tavollet',
            'description' => 'Teszt leiras',
            'active' => true,
            'order_index' => 15,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'rendkivuli_tavollet')
        ->assertJsonPath('data.company_id', $company->id);
});

it('azonos nev mellett suffixelt kodot general ugyanazon company-ban', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.leave_categories.store'), [
            'name' => 'Rendkivuli tavollet',
            'description' => null,
            'active' => true,
            'order_index' => 10,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'rendkivuli_tavollet');

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.leave_categories.store'), [
            'name' => 'Rendkivuli tavollet',
            'description' => null,
            'active' => true,
            'order_index' => 20,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'rendkivuli_tavollet_2');
});

<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkPattern;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendéget a munkarend fetch végpontról', function (): void {
    $this->get(route('work_patterns.fetch'))->assertRedirect();
});

it('megtagadja a fetch műveletet viewAny jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->getJson(route('work_patterns.fetch', ['company_id' => 1]))
        ->assertForbidden();
});

it('company_id alapján szűri a munkarendeket', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $c1 = Company::factory()->create();
    $c2 = Company::factory()->create();

    WorkPattern::factory()->count(3)->create(['company_id' => $c1->id, 'active' => true]);
    WorkPattern::factory()->count(2)->create(['company_id' => $c2->id, 'active' => true]);

    $resp = $this->actingAs($user)->getJson(route('work_patterns.fetch', [
        'company_id' => $c1->id,
        'page' => 1,
        'per_page' => 10,
    ]));

    $resp->assertOk()->assertJsonStructure([
        'data',
        'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        'filter',
    ]);

    expect($resp->json('data'))->toHaveCount(3);
    foreach ($resp->json('data') as $row) {
        expect($row['company_id'])->toBe($c1->id);
    }
});

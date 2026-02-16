<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkSchedule;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez a beosztások lekéréséhez', function (): void {
    $this->get(route('work_schedules.fetch'))->assertRedirect();
});

it('denies work schedules fetch if user lacks viewAny permission', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->getJson(route('work_schedules.fetch'))
        ->assertForbidden();
});

it('supports tenant scoping by company_id filter', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $c1 = Company::factory()->create();
    $c2 = Company::factory()->create();

    WorkSchedule::factory()->count(3)->create(['company_id' => $c1->id, 'status' => 'draft']);
    WorkSchedule::factory()->count(2)->create(['company_id' => $c2->id, 'status' => 'draft']);

    $resp = $this
        ->actingAs($user)
        ->getJson(route('work_schedules.fetch', [
            'company_id' => $c1->id,
            'page' => 1,
            'per_page' => 10,
        ]));

    $resp->assertOk();

    expect($resp->json('data'))->toHaveCount(3);
    foreach ($resp->json('data') as $row) {
        expect($row['company_id'])->toBe($c1->id);
    }
});

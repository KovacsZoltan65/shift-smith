<?php

declare(strict_types=1);

use App\Models\TenantGroup;
use App\Models\Company;
use App\Models\WorkSchedule;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez a beosztások lekéréséhez', function (): void {
    $this->get(route('work_schedules.fetch'))->assertRedirect();
});

it('megtagadja a beosztások lekérését, ha nincs viewAny jogosultság', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->getJson(route('work_schedules.fetch'))
        ->assertForbidden();
});

it('visszaadja a beosztás listát meta és filter adatokkal, működő szűréssel', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $user = $this->createAdminUser($companyA);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    WorkSchedule::factory()->create([
        'company_id' => $companyA->id,
        'name' => 'Alpha schedule',
        'status' => 'draft',
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
    ]);

    WorkSchedule::factory()->create([
        'company_id' => $companyA->id,
        'name' => 'Beta schedule',
        'status' => 'published',
        'date_from' => '2026-02-11',
        'date_to' => '2026-02-20',
    ]);

    WorkSchedule::factory()->count(3)->create([
        'company_id' => $companyB->id,
        'status' => 'draft',
    ]);

    $resp = $this
        ->actingAsUserInCompany($user, $companyA)
        ->getJson(route('work_schedules.fetch', [
            'company_id' => $companyA->id,
            'search' => 'alpha',
            'status' => 'draft',
            'page' => 1,
            'per_page' => 50,
            'order' => 'desc',
        ]));

    $resp
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'filter',
        ]);

    expect($resp->json('meta.total'))->toBe(1);
    expect($resp->json('data.0.name'))->toBe('Alpha schedule');
    expect($resp->json('data.0.company_id'))->toBe($companyA->id);
});

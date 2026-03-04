<?php

declare(strict_types=1);

use App\Models\WorkSchedule;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('frissíti a scoped munkabeosztást és bumpolja a cache verziókat', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $workSchedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'name' => 'Régi beosztás',
        'status' => 'draft',
    ]);

    $tenant->makeCurrent();
    $versioner = app(CacheVersionService::class);
    Cache::forever("v:company:{$company->id}:work_schedules", 1);
    Cache::forever('v:selectors.work_schedules', 1);

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('work_schedules.update', ['id' => $workSchedule->id]), [
            'company_id' => $company->id,
            'name' => 'Frissített beosztás',
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'status' => 'published',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    $this->assertDatabaseHas('work_schedules', [
        'id' => $workSchedule->id,
        'name' => 'Frissített beosztás',
        'status' => 'published',
    ]);

    expect($versioner->get("company:{$company->id}:work_schedules"))->toBe(2);
    expect($versioner->get('selectors.work_schedules'))->toBe(2);
});

it('nem enged cross-tenant frissítést', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();

    $user = $this->createAdminUser($companyA);
    $foreign = WorkSchedule::factory()->create(['company_id' => $companyB->id]);

    $tenantA->makeCurrent();

    $this->actingAsUserInCompany($user, $companyA)
        ->putJson(route('work_schedules.update', ['id' => $foreign->id]), [
            'company_id' => $companyA->id,
            'name' => 'Tiltott',
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'status' => 'draft',
        ])
        ->assertNotFound();
});

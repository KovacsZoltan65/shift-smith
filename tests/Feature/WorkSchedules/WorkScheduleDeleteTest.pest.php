<?php

declare(strict_types=1);

use App\Models\WorkSchedule;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('soft delete-olja a scoped munkabeosztást és bumpolja a cache verziókat', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $workSchedule = WorkSchedule::factory()->create(['company_id' => $company->id]);

    $tenant->makeCurrent();
    $versioner = app(CacheVersionService::class);
    Cache::forever("v:company:{$company->id}:work_schedules", 1);
    Cache::forever('v:selectors.work_schedules', 1);

    $this->actingAsUserInCompany($user, $company)
        ->deleteJson(route('work_schedules.destroy', ['id' => $workSchedule->id]), [
            'company_id' => $company->id,
        ])
        ->assertOk()
        ->assertJsonPath('deleted', true);

    $this->assertSoftDeleted('work_schedules', ['id' => $workSchedule->id]);
    expect($versioner->get("company:{$company->id}:work_schedules"))->toBe(2);
    expect($versioner->get('selectors.work_schedules'))->toBe(2);
});

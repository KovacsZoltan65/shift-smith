<?php

declare(strict_types=1);

use App\Models\WorkSchedule;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('soft delete-olja bulkban a scoped munkabeosztásokat és bumpolja a cache verziókat', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $rows = WorkSchedule::factory()->count(3)->create(['company_id' => $company->id]);

    $tenant->makeCurrent();
    $versioner = app(CacheVersionService::class);
    Cache::forever("v:company:{$company->id}:work_schedules", 1);
    Cache::forever('v:selectors.work_schedules', 1);

    $this->actingAsUserInCompany($user, $company)
        ->deleteJson(route('work_schedules.destroy_bulk'), [
            'company_id' => $company->id,
            'ids' => $rows->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJsonPath('deleted', 3);

    foreach ($rows as $row) {
        $this->assertSoftDeleted('work_schedules', ['id' => $row->id]);
    }

    expect($versioner->get("company:{$company->id}:work_schedules"))->toBe(2);
    expect($versioner->get('selectors.work_schedules'))->toBe(2);
});

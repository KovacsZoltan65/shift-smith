<?php

declare(strict_types=1);

use App\Interfaces\WorkScheduleRepositoryInterface;
use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('repository fetch only returns rows from the requested company scope', function (): void {
    $tenantGroup = TenantGroup::factory()->create();
    $companyOne = Company::factory()->create(['tenant_group_id' => $tenantGroup->id]);
    $companyTwo = Company::factory()->create(['tenant_group_id' => $tenantGroup->id]);

    WorkSchedule::factory()->count(2)->create(['company_id' => $companyOne->id, 'status' => 'draft']);
    WorkSchedule::factory()->count(3)->create(['company_id' => $companyTwo->id, 'status' => 'draft']);

    $request = Request::create('/work_schedules/fetch', 'GET', [
        'page' => 1,
        'per_page' => 50,
    ]);

    /** @var WorkScheduleRepositoryInterface $repository */
    $repository = app(WorkScheduleRepositoryInterface::class);
    $result = $repository->fetch($request, $companyOne->id);

    expect($result->total())->toBe(2);
    expect(collect($result->items())->every(
        fn (WorkSchedule $workSchedule): bool => (int) $workSchedule->company_id === $companyOne->id
    ))->toBeTrue();
});

it('findOrFailScoped path returns 404 on company mismatch', function (): void {
    $tenantGroup = TenantGroup::factory()->create();
    $companyOne = Company::factory()->create(['tenant_group_id' => $tenantGroup->id]);
    $companyTwo = Company::factory()->create(['tenant_group_id' => $tenantGroup->id]);

    $scheduleInCompanyTwo = WorkSchedule::factory()->create([
        'company_id' => $companyTwo->id,
        'status' => 'draft',
    ]);

    $user = $this->createAdminUser($companyOne);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $companyOne->id,
            'current_tenant_group_id' => $tenantGroup->id,
        ])
        ->getJson(route('work_schedules.by_id', ['id' => $scheduleInCompanyTwo->id]))
        ->assertNotFound();
});

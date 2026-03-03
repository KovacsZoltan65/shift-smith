<?php

declare(strict_types=1);

use App\Models\LeaveCategory;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('soft delete-olja a sajat company leave category rekordjat', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $category = LeaveCategory::factory()->create([
        'company_id' => $company->id,
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->deleteJson(route('admin.leave_categories.destroy', $category->id))
        ->assertOk()
        ->assertJsonPath('deleted', true);

    $this->assertSoftDeleted('leave_categories', [
        'id' => $category->id,
        'company_id' => $company->id,
    ]);
});

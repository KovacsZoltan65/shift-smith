<?php

declare(strict_types=1);

use App\Services\Cache\CacheVersionService;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('menti a current company + self scope user settinget és bumpolja a cache-t', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $versioner = app(CacheVersionService::class);
    $before = $versioner->get("user_settings:{$company->id}:{$user->id}:fetch");

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.user_settings.store'), [
            'key' => 'user.theme',
            'type' => 'string',
            'group' => 'ui',
            'value' => 'compact',
        ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $user->id);

    expect($versioner->get("user_settings:{$company->id}:{$user->id}:fetch"))->toBeGreaterThan($before);
});

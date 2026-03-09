<?php

declare(strict_types=1);

use App\Models\User;

it('authorized HQ user can list tenant groups', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->get(route('hq.tenant_groups.index'));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('TenantGroups/Index'));
});

it('unauthorized user cannot list tenant groups', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('hq.tenant_groups.index'));

    $response->assertForbidden();
});

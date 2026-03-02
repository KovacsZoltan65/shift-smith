<?php

declare(strict_types=1);

use App\Models\Admin\Permission;
use App\Support\PermissionCatalog;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('a P0 kanonikus permission inventory minden eleme létezik seed után', function (): void {
    $existing = Permission::query()
        ->whereIn('name', PermissionCatalog::p0Flat())
        ->pluck('name')
        ->values()
        ->all();

    expect($existing)->toEqualCanonicalizing(PermissionCatalog::p0Flat());
});

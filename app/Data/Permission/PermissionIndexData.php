<?php

namespace App\Data\Permission;

use App\Models\Admin\Permission;
use Spatie\LaravelData\Data;

/**
 * Minimal jogosultság DTO táblázatos listázáshoz.
 */
class PermissionIndexData extends Data
{
    /**
     * @param int $id Jogosultság azonosító
     * @param string $name Jogosultság név
     * @param string $guard_name Guard név
     * @param ?string $created_at Létrehozás ideje
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $guard_name,
        public ?string $created_at = null,
    ) {}

    /**
     * DTO előállítása modelből.
     *
     * @param Permission $permission Jogosultság model
     * @return self
     */
    public static function fromModel(Permission $permission): self
    {
        return new self(
            id: (int) $permission->id,
            name: (string) $permission->name,
            guard_name: (string) $permission->guard_name,
            created_at: optional($permission->created_at)?->toDateTimeString(),
        );
    }
}

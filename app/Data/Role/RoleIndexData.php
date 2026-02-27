<?php

namespace App\Data\Role;

use App\Models\Admin\Role;
use Spatie\LaravelData\Data;

/**
 * Minimal szerepkör DTO táblázatos listázáshoz.
 */
class RoleIndexData extends Data
{
    /**
     * @param int $id Szerepkör azonosító
     * @param string $name Szerepkör név
     * @param string $guard_name Guard név
     * @param int $users_count Hozzárendelt felhasználók száma
     * @param ?string $created_at Létrehozás ideje
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $guard_name,
        public int $users_count = 0,
        public array $user_ids = [],
        public ?string $created_at = null,
    ) {}

    /**
     * DTO előállítása modelből.
     *
     * @param Role $role Szerepkör model
     * @return self
     */
    public static function fromModel(Role $role): self
    {
        return new self(
            id: (int) $role->id,
            name: (string) $role->name,
            guard_name: (string) $role->guard_name,
            users_count: (int) ($role->users_count ?? 0),
            user_ids: $role->relationLoaded('users')
                ? $role->users->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
                : [],
            created_at: optional($role->created_at)?->toDateTimeString(),
        );
    }
}

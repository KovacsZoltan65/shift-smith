<?php

namespace App\Data\Role;

use App\Models\Admin\Role;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Support\Validation\References\RouteParameterReference;

/**
 * Teljes szerepkör DTO create/update és részletes megjelenítés célra.
 */
class RoleData extends Data
{
    /**
     * @param ?int $id Szerepkör azonosító
     * @param string $name Szerepkör név
     * @param string $guard_name Guard név
     * @param list<int> $permission_ids Jogosultság azonosítók
     * @param list<int> $user_ids Felhasználó azonosítók
     * @param ?string $createdAt Létrehozás ideje
     * @param ?string $updatedAt Frissítés ideje
     */
    public function __construct(
        public ?int $id,

        #[Required, StringType, Max(100), Unique('roles', 'name', null, new RouteParameterReference('id', null, true))]
        public string $name,

        #[Required, StringType, Max(50)]
        public string $guard_name,

        #[ArrayType]
        public array $permission_ids = [],

        #[ArrayType]
        public array $user_ids = [],

        #[MapName('created_at')]
        public ?string $createdAt = null,

        #[MapName('updated_at')]
        public ?string $updatedAt = null,
    ) {}

    /**
     * DTO előállítása modelből.
     *
     * @param Role $role Szerepkör model
     * @return self
     */
    public static function fromModel(Role $role): self
    {
        $role->loadMissing(['permissions', 'users:id']);

        return new self(
            id: (int) $role->id,
            name: (string) $role->name,
            guard_name: (string) $role->guard_name,
            permission_ids: $role->permissions->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            user_ids: $role->users->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            createdAt: optional($role->created_at)?->toDateTimeString(),
            updatedAt: optional($role->updated_at)?->toDateTimeString(),
        );
    }
}

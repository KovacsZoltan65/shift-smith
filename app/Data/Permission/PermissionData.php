<?php

namespace App\Data\Permission;

use App\Models\Admin\Permission;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\References\RouteParameterReference;

/**
 * Teljes jogosultság DTO create/update és részletes megjelenítés célra.
 */
class PermissionData extends Data
{
    /**
     * @param ?int $id Jogosultság azonosító
     * @param string $name Jogosultság név
     * @param string $guard_name Guard név
     * @param ?string $createdAt Létrehozás ideje
     * @param ?string $updatedAt Frissítés ideje
     */
    public function __construct(
        public ?int $id,

        #[Required, StringType, Max(100), Unique('permissions', 'name', null, new RouteParameterReference('id', null, true))]
        public string $name,

        #[Required, StringType, Max(50)]
        public string $guard_name,

        #[MapName('created_at')]
        public ?string $createdAt = null,

        #[MapName('updated_at')]
        public ?string $updatedAt = null,
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
            createdAt: optional($permission->created_at)?->toDateTimeString(),
            updatedAt: optional($permission->updated_at)?->toDateTimeString(),
        );
    }
}

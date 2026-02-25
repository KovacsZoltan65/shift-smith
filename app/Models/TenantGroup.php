<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

/**
 * @method static self|null current()
 * @method static self|null forgetCurrent()
 * @method static bool checkCurrent()
 * @method $this makeCurrent()
 */
class TenantGroup extends BaseTenant
{
    /** @var array<int,string> */
    protected $fillable = [
        'name',
        'slug',
        'database_name',
        'active',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'active' => 'bool',
    ];

    /**
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function getDatabaseName(): string
    {
        if (is_string($this->database_name) && $this->database_name !== '') {
            return $this->database_name;
        }

        return (string) config('database.connections.'.config('database.default').'.database', '');
    }
}

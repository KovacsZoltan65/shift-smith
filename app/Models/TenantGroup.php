<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

class TenantGroup extends Model implements IsTenant
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory;
    use UsesLandlordConnection;
    use ImplementsTenant;

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

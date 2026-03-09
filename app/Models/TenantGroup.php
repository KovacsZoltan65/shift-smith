<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

/**
 * A platform valódi tenant modellje.
 *
 * Egy TenantGroup több company rekordot birtokol. A domain adatok ettől függetlenül
 * company scope alatt maradnak, miközben ez a modell a tenant feloldás és a későbbi
 * multi-database működés alapja.
 *
 * @method static self|null current()
 * @method static self|null forgetCurrent()
 * @method static bool checkCurrent()
 * @method $this makeCurrent()
 */
class TenantGroup extends BaseTenant
{
    /** @use HasFactory<TenantGroupFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    /** @var array<int,string> */
    protected $fillable = [
        'name',
        'code',
        'slug',
        'database_name',
        'status',
        'notes',
        'active',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'active' => 'bool',
    ];

    /** @var array<int,string> */
    public const SORTABLE = [
        'id',
        'name',
        'code',
        'status',
        'active',
        'created_at',
        'updated_at',
    ];

    /**
     * A code és slug normalizálása a modell határán történik, így a seederek, parancsok
     * és a landlord CRUD ugyanazokat az egyediségszabályokat követik.
     */
    protected static function booted(): void
    {
        static::creating(function (self $tenantGroup): void {
            $tenantGroup->name = trim((string) $tenantGroup->name);
            $tenantGroup->code = self::makeUniqueCode((string) $tenantGroup->code, (string) $tenantGroup->name);
            $tenantGroup->slug = self::makeUniqueSlug((string) $tenantGroup->slug, (string) $tenantGroup->code);
        });

        static::updating(function (self $tenantGroup): void {
            $tenantGroup->name = trim((string) $tenantGroup->name);

            if ($tenantGroup->isDirty('code') || blank($tenantGroup->code)) {
                $tenantGroup->code = self::makeUniqueCode((string) $tenantGroup->code, (string) $tenantGroup->name, (int) $tenantGroup->id);
            }

            if ($tenantGroup->isDirty('slug') || blank($tenantGroup->slug)) {
                $tenantGroup->slug = self::makeUniqueSlug((string) $tenantGroup->slug, (string) $tenantGroup->code, (int) $tenantGroup->id);
            }
        });
    }

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

        // Single-database módban a default kapcsolat adatbázisára esünk vissza,
        // de a Spatie tenant szerződés közben készen áll a későbbi dedikált tenant adatbázisokra.
        return (string) config('database.connections.'.config('database.default').'.database', '');
    }

    public static function getTag(): string
    {
        return 'landlord:tenant-groups';
    }

    /**
     * @return array<int,string>
     */
    public static function getSortable(): array
    {
        return self::SORTABLE;
    }

    #[Override]
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    /**
     * Olyan stabil tenant kódot generál, amely soft delete mellett is egyedi marad.
     */
    private static function makeUniqueCode(string $code, string $name, ?int $ignoreId = null): string
    {
        $base = strtoupper(Str::of($code !== '' ? $code : $name)->replace('-', '_')->replace(' ', '_')->slug('_')->value());
        $base = trim($base, '_');
        $base = $base !== '' ? Str::limit($base, 50, '') : 'TENANT_GROUP';

        $candidate = $base;
        $counter = 1;

        while (self::query()
            ->withTrashed()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('code', $candidate)
            ->exists()) {
            $suffix = '_'.$counter;
            $candidate = Str::limit($base, 50 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $candidate;
    }

    /**
     * A publikus slugot aktív és archivált landlord rekordok között is egyedien tartja.
     */
    private static function makeUniqueSlug(string $slug, string $code, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug !== '' ? $slug : $code);
        $base = $base !== '' ? Str::limit($base, 150, '') : 'tenant-group';

        $candidate = $base;
        $counter = 1;

        while (self::query()
            ->withTrashed()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $suffix = '-'.$counter;
            $candidate = Str::limit($base, 150 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $candidate;
    }
}

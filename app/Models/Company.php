<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Cég model osztály
 * 
 * Cégek adatainak tárolása és kezelése.
 * Soft delete támogatással, activity log naplózással.
 * Kapcsolódó dolgozók (employees) kezelése.
 * 
 * @property int|string $id Cég azonosító
 * @property string $name Cég neve
 * @property string $name_lc Cég neve kisbetűvel (kereséshez)
 * @property string $email Email cím
 * @property string $address Cím
 * @property string $phone Telefonszám
 * @property boolean $active Aktív státusz
 * @property \Illuminate\Support\Carbon|null $deleted_at Törlés időpontja (soft delete)
 * @property \Illuminate\Support\Carbon $created_at Létrehozás időpontja
 * @property \Illuminate\Support\Carbon $updated_at Módosítás időpontja
 */
class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['tenant_group_id', 'name', 'email', 'address', 'phone', 'active'];
    
    /** @var list<string> */
    protected $guarded = ['name_lc'];

    /** @var array<string,string> */
    protected $casts = [
        'tenant_group_id' => 'int',
        'active' => 'boolean',
    ];

    /** @var array<int,string> */
    public const SORTABLE = [
        'id', 'name', 'email', 'address', 'phone',
    ];

    /*
     * ========================= LOGOLÁS =========================
     */
    /** @var array<int,string> */
    protected static array $logAttributes = ['*'];
    
    protected static bool $logOnlyDirty = true;
    
    protected static string $logName = 'companies';
    
    /** @var array<int,string> */
    protected static array $recordEvents = ['created', 'updated', 'deleted'];
    
    /**
     * Activity log név lekérése
     * 
     * @param string $eventName Esemény neve (created, updated, deleted)
     * @return string Log csatorna neve
     */
    public function getLogNameToUse(string $eventName = ''): string
    {
        return static::$logName ?? 'default';
    }

    /**
     * Cache tag név lekérése
     * 
     * @return string Cache tag azonosító
     */
    public static function getTag(): string
    {
        return static::$logName;
    }
    
    #[Override]
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->dontLogIfAttributesChangedOnly(['remember_token'])
            ->logExcept(['password', 'remember_token'])
            //->dontLogAttributes(['password'])
                ;
    }
    
    /**
     * ===========================================================
     */

    /**
     * Rendezhető mezők listája
     * 
     * @return array<int,string> Rendezhető oszlopnevek
     */
    public static function getSortable(): array
    {
        return self::SORTABLE;
    }

    /**
     * Aktív cégek szűrése
     * 
     * @param  Builder<Company>  $query Query builder
     * @return Builder<Company> Szűrt query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', APP_ACTIVE);
    }

    /**
     * Keresés név, email és telefon mezőkben
     * 
     * Request paraméterek alapján szűr név, email és telefon mezőkre.
     * 
     * @param  Builder<self>  $query Query builder
     * @param  Request  $request HTTP kérés objektum
     * @return Builder<self> Szűrt query
     */
    public function scopeSearch(Builder $query, Request $request): Builder
    {
        return $query->when($request->input('search'), function(Builder $q) use ($request): void {
            $q->when($request->input('name'), fn (Builder $qq): Builder => $qq->where('name', 'like', "%{$request->input('name')}%"));

            $q->when($request->input('email'), fn (Builder $qq): Builder => $qq->where('email', 'like', "%{$request->input('email')}%"));

            $q->when($request->input('phone'), fn (Builder $qq): Builder => $qq->where('phone', 'like', "%{$request->input('phone')}%"));
        });
    }
    /*
    public function scopeSearch(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->input('search'), function (Builder $q) use ($request): void {
                $q->where(function (Builder $qq) use ($request): void {
                    $qq->where('name', 'like', "%{$request->input('search')}%");
                });
            })
            ->when($request->input('email'), fn (Builder $q): Builder => $q->where('email', 'like', "%{$request->input('email')}%"))
            ->when($request->input('phone'), fn (Builder $q): Builder => $q->where('phone', 'like', "%{$request->input('phone')}%"));
    }
    */




    /*
     * ========================= RELATIONS =========================
     */

    /**
     * Céghez tartozó dolgozók kapcsolata (N:M).
     *
     * @return BelongsToMany<Employee, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'company_employee')
            ->withPivot(['active'])
            ->withTimestamps();
    }

    /**
     * Legacy "home company" kapcsolat employees.company_id alapján.
     *
     * @return HasMany<Employee, $this>
     */
    public function homeEmployees(): HasMany
    {
        return $this->hasMany(Employee::class, 'company_id');
    }

    /**
     * Céghez rendelt felhasználók kapcsolata.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }

    /**
     * Cég tenant csoport kapcsolata.
     *
     * @return BelongsTo<TenantGroup, $this>
     */
    public function tenantGroup(): BelongsTo
    {
        return $this->belongsTo(TenantGroup::class);
    }

    /**
     * ===========================================================
     */
    
    /*
     * ========================= MUTATORS =========================
     */
    /**
     * ===========================================================
     */
}

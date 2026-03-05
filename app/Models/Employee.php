<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Org\EmployeeSupervisor;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Dolgozó model osztály
 * 
 * Dolgozók adatainak tárolása és kezelése.
 * Soft delete támogatással, activity log naplózással.
 * Kapcsolódik egy céghez (company).
 * 
 * @property int $id Dolgozó azonosító
 * @property int $company_id Cég azonosító
 * @property string $first_name Keresztnév
 * @property string|null $last_name Vezetéknév
 * @property string|null $email Email cím
 * @property string|null $address Cím
 * @property int|null $position_id Pozíció azonosító
     * @property string|null $phone Telefonszám
     * @property string|null $hired_at Felvétel dátuma
     * @property \Illuminate\Support\Carbon|null $birth_date Születési dátum
     * @property int $children_count Gyermekek száma
     * @property int $disabled_children_count Fogyatékos gyermekek száma
     * @property bool $is_disabled Megváltozott munkaképesség / fogyatékosság státusz
     * @property int $active Aktív státusz
 * @property \App\Models\Company|null $company Kapcsolódó cég
 * @property string $name Teljes név (computed)
 * @property string|null $company_name Cég neve (computed)
 * @property \Illuminate\Support\Carbon|null $deleted_at Törlés időpontja (soft delete)
 * @property \Illuminate\Support\Carbon $created_at Létrehozás időpontja
 * @property \Illuminate\Support\Carbon $updated_at Módosítás időpontja
 * @method static EmployeeFactory factory(...$parameters)
 */
class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'company_id', 'first_name', 'last_name', 'email', 'address',
        'position_id', 'org_level', 'phone', 'hired_at', 'birth_date',
        'children_count', 'disabled_children_count', 'is_disabled', 'active',
    ];

    protected $casts = [
        'position_id' => 'int',
        'org_level' => 'string',
        'hired_at' => 'date',
        'birth_date' => 'date',
        'children_count' => 'int',
        'disabled_children_count' => 'int',
        'is_disabled' => 'bool',
        'active' => 'bool',
    ];

    /** @var array<int,string> */
    public const SORTABLE = [
        'id',
        'name',
        'first_name',
        'last_name',
        'email',
        'hired_at',
        'active',
        'created_at',
    ];

    public const ORG_LEVEL_CEO = 'ceo';
    public const ORG_LEVEL_MANAGER = 'manager';
    public const ORG_LEVEL_DEPARTMENT_HEAD = 'department_head';
    public const ORG_LEVEL_SHIFT_LEAD = 'shift_lead';
    public const ORG_LEVEL_TEAM_LEAD = 'team_lead';
    public const ORG_LEVEL_STAFF = 'staff';

    /** @var list<string> */
    public const ORG_LEVELS = [
        self::ORG_LEVEL_CEO,
        self::ORG_LEVEL_MANAGER,
        self::ORG_LEVEL_DEPARTMENT_HEAD,
        self::ORG_LEVEL_SHIFT_LEAD,
        self::ORG_LEVEL_TEAM_LEAD,
        self::ORG_LEVEL_STAFF,
    ];

    protected $appends = ['name', 'company_name'];

    /**
     * Cég neve accessor
     * 
     * @return string|null Kapcsolódó cég neve vagy null
     */
    public function getCompanyNameAttribute(): ?string
    {
        return $this->company?->name;
    }

    /*
     * ========================= LOGOLÁS =========================
     */
    /** @var array<int,string> */
    protected static array $logAttributes = ['*'];
    
    protected static bool $logOnlyDirty = true;
    
    protected static string $logName = 'employees';
    
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
     * Teljes név accessor
     * 
     * Keresztnév és vezetéknév összefűzése.
     * 
     * @return string Teljes név
     */
    public function getNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Aktív dolgozók szűrése
     * 
     * @param  Builder<Employee>  $query Query builder
     * @return Builder<Employee> Szűrt query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', APP_ACTIVE);
    }

    /**
     * Keresés több mezőben
     * 
     * Keres név, email, telefon és beosztás mezőkben.
     * 
     * @param  Builder<Employee>  $query Query builder
     * @param  string  $search Keresési kifejezés
     * @return Builder<Employee> Szűrt query
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $s = trim($search);
        if ($s === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($s) {
            $q->where('first_name', 'like', "%{$s}%")
              ->orWhere('last_name', 'like', "%{$s}%")
              ->orWhere('email', 'like', "%{$s}%")
              ->orWhere('phone', 'like', "%{$s}%")
              ->orWhereHas('position', fn (Builder $qq) => $qq->where('name', 'like', "%{$s}%"));
        });
    }

    /*
     * ========================= RELATIONS =========================
     */

    /**
     * Dolgozó céghez tartozása
     * 
     * Egy dolgozó egy céghez tartozik (N:1 kapcsolat).
     * 
     * @return BelongsTo<Company, $this> Cég kapcsolata
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Dolgozóhoz tartozó cégek (N:M).
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_employee')
            ->withPivot(['active'])
            ->withTimestamps();
    }

    /**
     * Dolgozóhoz tartozó felhasználók (N:M).
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_employee')
            ->withPivot(['company_id', 'active'])
            ->withTimestamps();
    }

    /**
     * Dolgozó pozíció kapcsolata.
     *
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Dolgozó munkarend hozzárendelései.
     *
     * @return HasMany<EmployeeWorkPattern, $this> Munkarend hozzárendelések
     */
    public function workPatterns(): HasMany
    {
        return $this->hasMany(EmployeeWorkPattern::class);
    }

    /**
     * Dolgozó szabadság profilja.
     *
     * @return HasOne<EmployeeProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    /**
     * @return HasOne<EmployeeSupervisor, $this>
     */
    public function currentSupervisorRelation(): HasOne
    {
        return $this->hasOne(EmployeeSupervisor::class, 'employee_id')
            ->whereColumn('employee_supervisors.company_id', 'employees.company_id')
            ->whereNull('valid_to')
            ->latestOfMany('valid_from');
    }

    /**
     * @return HasMany<EmployeeSupervisor, $this>
     */
    public function supervisorHistory(): HasMany
    {
        return $this->hasMany(EmployeeSupervisor::class, 'employee_id')
            ->whereColumn('employee_supervisors.company_id', 'employees.company_id')
            ->orderByDesc('valid_from')
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<EmployeeSupervisor, $this>
     */
    public function directSubordinates(): HasMany
    {
        return $this->hasMany(EmployeeSupervisor::class, 'supervisor_employee_id')
            ->whereColumn('employee_supervisors.company_id', 'employees.company_id')
            ->whereNull('valid_to')
            ->orderBy('valid_from');
    }

    /**
     * ===========================================================
     */
}

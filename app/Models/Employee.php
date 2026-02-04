<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $company_id
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $address
 * @property string|null $position
 * @property string|null $phone
 * @property string|null $hired_at
 * @property int $active
 * @property \App\Models\Company|null $company
 * @method static EmployeeFactory factory(...$parameters)
 */
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'company_id', 'first_name', 'last_name', 'email', 'address',
        'position', 'phone', 'hired_at', 'active',
    ];

    protected $casts = [
        'hired_at' => 'date',
        'active' => 'bool',
    ];

    /** @var array<int,string> */
    public const SORTABLE = [
        'id',
        'first_name',
        'last_name',
        'email',
        'hired_at',
        'active',
        'created_at',
    ];

    protected $appends = ['name', 'company_name'];

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
    
    public function getLogNameToUse(string $eventName = ''): string
    {
        return static::$logName ?? 'default';
    }

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

    /** @return array<int,string> */
    public static function getSortable(): array
    {
        return self::SORTABLE;
    }

    public function getNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', APP_ACTIVE);
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
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
              ->orWhere('position', 'like', "%{$s}%");
        });
    }

    /*
     * ========================= RELATIONS =========================
     */

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * ===========================================================
     */
}
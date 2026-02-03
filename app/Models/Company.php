<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** 
 * @property int|string $id
 * @property string $name
 * @property string $name_lc
 * @property string $email
 * @property string $address
 * @property string $phone
 * @property boolean $active
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory,
        LogsActivity,
        SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'address', 'phone', 'active'];
    
    /** @var list<string> */
    protected $guarded = ['name_lc'];

    /** @var array<string,string> */
    protected $casts = [ 'active' => 'boolean', ];

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

    /**
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', APP_ACTIVE);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
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
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
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

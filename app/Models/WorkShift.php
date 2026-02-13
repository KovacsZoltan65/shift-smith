<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** 
 * @property int|string $id
 * @property int|string company_id
 * @property string $name
 * @property string $start_time
 * @property string $end_time
 * @property boolean $active
 */
class WorkShift extends Model
{
    /** @use HasFactory<WorkShiftFactory> */
    use HasFactory,
        SoftDeletes,
        LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'name',
        'start_time',
        'end_time',
        'active',
    ];

    /** @var list<string> */
    protected $guarded = ['name_lc'];
    
    /** @var array<string,string> */
    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'active' => 'boolean',
    ];
    
    /** @var array<int,string> */
    public const SORTABLE = ['company_id', 'name', 'start_time', 'end_time', 'active',];
    
    /*
     * ========================= LOGOLÁS =========================
     */
    /** @var array<int,string> */
    protected static array $logAttributes = ['*'];
    
    protected static bool $logOnlyDirty = true;
    
    protected static string $logName = 'work_shifts';
    
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
     * @param  Builder<WorkShift>  $query
     * @return Builder<WorkShift>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', APP_ACTIVE);
    }
    
    /**
     * @param  Builder<WorkShift>  $query
     * @return Builder<WorkShift>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $s = trim($search);
        if ($s === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($s) {
            $q->where('name', 'like', "%{$s}%")
              ->orWhere('start_time', 'like', "%{$s}%")
              ->orWhere('end_time', 'like', "%{$s}%");
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

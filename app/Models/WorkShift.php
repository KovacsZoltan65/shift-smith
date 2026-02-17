<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Műszak model osztály
 * 
 * Műszakok (work shifts) adatainak tárolása és kezelése.
 * Soft delete támogatással, activity log naplózással.
 * Kapcsolódik egy céghez (company).
 * 
 * @property int|string $id Műszak azonosító
 * @property int|string $company_id Cég azonosító
 * @property string $name Műszak neve
 * @property string $start_time Kezdési időpont (HH:MM)
 * @property string $end_time Befejezési időpont (HH:MM)
 * @property int $work_time_minutes Munka idő percekben
 * @property int $break_minutes Szünet idő percekben
 * @property boolean $active Aktív státusz
 * @property \Illuminate\Support\Carbon|null $deleted_at Törlés időpontja (soft delete)
 * @property \Illuminate\Support\Carbon $created_at Létrehozás időpontja
 * @property \Illuminate\Support\Carbon $updated_at Módosítás időpontja
 */
class WorkShift extends Model
{
    /** @use HasFactory<\Database\Factories\WorkShiftFactory> */
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'name',
        'start_time',
        'end_time',
        'work_time_minutes',
        'break_minutes',
        'active',
    ];

    /** @var list<string> */
    protected $guarded = ['name_lc'];
    
    /** @var array<string,string> */
    protected $casts = [
        'active' => 'bool',
        'start_time' => 'string',
        'end_time' => 'string',
        'work_time_minutes' => 'int',
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
     * Aktív műszakok szűrése
     * 
     * @param  Builder<WorkShift>  $query Query builder
     * @return Builder<WorkShift> Szűrt query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', APP_ACTIVE);
    }
    
    /**
     * Keresés név és időpont mezőkben
     * 
     * Keres név, kezdési és befejezési időpont mezőkben.
     * 
     * @param  Builder<WorkShift>  $query Query builder
     * @param  string  $search Keresési kifejezés
     * @return Builder<WorkShift> Szűrt query
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
     * Műszak céghez tartozása
     * 
     * Egy műszak egy céghez tartozik (N:1 kapcsolat).
     * 
     * @return BelongsTo<Company, $this> Cég kapcsolata
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * ===========================================================
     */
}

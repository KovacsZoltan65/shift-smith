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
 * Munkabeosztás model osztály
 * 
 * Munkabeosztások (work schedules) adatainak tárolása és kezelése.
 * Soft delete támogatással, activity log naplózással.
 * Kapcsolódik egy céghez (company).
 * 
 * @property int|string $id Munkabeosztás azonosító
 * @property int|string $company_id Cég azonosító
 * @property string $name Munkabeosztás neve
 * @property string $date_from Kezdő dátum (Y-m-d)
 * @property string $date_to Befejező dátum (Y-m-d)
 * @property string $status Státusz (draft, published, archived)
 * @property string|null $notes Megjegyzések
 * @property \Illuminate\Support\Carbon|null $deleted_at Törlés időpontja (soft delete)
 * @property \Illuminate\Support\Carbon $created_at Létrehozás időpontja
 * @property \Illuminate\Support\Carbon $updated_at Módosítás időpontja
 */
class WorkSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\WorkScheduleFactory> */
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'name',
        'date_from',
        'date_to',
        'status',
        'notes',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'company_id' => 'int',
        'date_from'  => 'date:Y-m-d',
        'date_to'    => 'date:Y-m-d',
        'status'     => 'string',
        'notes'      => 'string',
    ];

    /** @var array<int,string> */
    public const SORTABLE = ['id', 'company_id', 'name', 'date_from', 'date_to', 'status', 'created_at'];

    /*
     * ========================= LOGOLÁS =========================
     */
    /** @var array<int,string> */
    protected static array $logAttributes = ['*'];

    protected static bool $logOnlyDirty = true;

    protected static string $logName = 'work_schedules';

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
            ->logFillable();
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
     * Cég szerinti szűrés
     * 
     * @param Builder<WorkSchedule> $query Query builder
     * @param int $companyId Cég azonosító
     * @return Builder<WorkSchedule> Szűrt query
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Munkabeosztás céghez tartozása
     * 
     * Egy munkabeosztás egy céghez tartozik (N:1 kapcsolat).
     * 
     * @return BelongsTo<Company, $this> Cég kapcsolata
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

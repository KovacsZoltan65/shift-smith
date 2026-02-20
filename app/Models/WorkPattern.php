<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Munkarend model osztály.
 *
 * Hosszú távú munkavégzési szabályrendszert reprezentál cég szinten.
 *
 * @property int $id Munkarend azonosító
 * @property int $company_id Cég azonosító
 * @property string $name Munkarend neve
 * @property int $daily_work_minutes Napi munkaidő percben
 * @property int $break_minutes Szünet percben
 * @property string|null $core_start_time Törzsidő kezdete
 * @property string|null $core_end_time Törzsidő vége
 * @property bool $active Aktív státusz
 */
class WorkPattern extends Model
{
    /** @use HasFactory<\Database\Factories\WorkPatternFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    /** @var array<int,string> */
    protected $fillable = [
        'company_id',
        'name',
        'daily_work_minutes',
        'break_minutes',
        'core_start_time',
        'core_end_time',
        'active',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'company_id' => 'int',
        'daily_work_minutes' => 'int',
        'break_minutes' => 'int',
        'active' => 'bool',
    ];

    /** @var array<int,string> */
    public const SORTABLE = [
        'id',
        'name',
        'daily_work_minutes',
        'break_minutes',
        'active',
        'created_at',
    ];

    /** @var array<int,string> */
    protected static array $logAttributes = ['*'];

    protected static bool $logOnlyDirty = true;

    protected static string $logName = 'work_patterns';

    /** @var array<int,string> */
    protected static array $recordEvents = ['created', 'updated', 'deleted'];

    /**
     * Cache tag név lekérése.
     *
     * @return string Cache tag azonosító
     */
    public static function getTag(): string
    {
        return static::$logName;
    }

    /**
     * Rendezhető mezők listája.
     *
     * @return array<int,string> Rendezhető oszlopnevek
     */
    public static function getSortable(): array
    {
        return self::SORTABLE;
    }

    /**
     * Kapcsolódó cég.
     *
     * @return BelongsTo<Company, $this> Cég kapcsolat
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Kapcsolódó dolgozó-munkarend hozzárendelések.
     *
     * @return HasMany<EmployeeWorkPattern, $this> Hozzárendelések
     */
    public function employeeWorkPatterns(): HasMany
    {
        return $this->hasMany(EmployeeWorkPattern::class);
    }

    #[Override]
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Dolgozó-munkarend hozzárendelés model osztály.
 *
 * Dolgozóhoz rendelt munkarendet reprezentál érvényességi intervallummal.
 *
 * @property int $id Hozzárendelés azonosító
 * @property int $company_id Cég azonosító
 * @property int $employee_id Dolgozó azonosító
 * @property int $work_pattern_id Munkarend azonosító
 * @property string $date_from Érvényesség kezdete
 * @property string|null $date_to Érvényesség vége
 * @property bool $is_primary Elsődleges hozzárendelés jelző
 * @property array<string,mixed>|null $meta Kiegészítő meta adatok
 */
class EmployeeWorkPattern extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeWorkPatternFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'employee_work_patterns';

    /** @var array<int,string> */
    protected $fillable = [
        'company_id',
        'employee_id',
        'work_pattern_id',
        'date_from',
        'date_to',
        'is_primary',
        'meta',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'company_id' => 'int',
        'employee_id' => 'int',
        'work_pattern_id' => 'int',
        'date_from' => 'date:Y-m-d',
        'date_to' => 'date:Y-m-d',
        'is_primary' => 'bool',
        'meta' => 'array',
    ];

    /** @var array<int,string> */
    protected static array $logAttributes = ['*'];

    protected static bool $logOnlyDirty = true;

    protected static string $logName = 'employee_work_patterns';

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
     * Kapcsolódó cég.
     *
     * @return BelongsTo<Company, $this> Cég kapcsolat
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Kapcsolódó dolgozó.
     *
     * @return BelongsTo<Employee, $this> Dolgozó kapcsolat
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Kapcsolódó munkarend.
     *
     * @return BelongsTo<WorkPattern, $this> Munkarend kapcsolat
     */
    public function workPattern(): BelongsTo
    {
        return $this->belongsTo(WorkPattern::class);
    }

    #[Override]
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }
}

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
 * WorkScheduleAssignment model.
 *
 * Schedule-alapú dolgozó kiosztások tárolása nap szinten.
 *
 * @property int $id
 * @property int $company_id
 * @property int $work_schedule_id
 * @property int $employee_id
 * @property int $work_shift_id
 * @property \Illuminate\Support\Carbon $day
 * @property string|null $start_time
 * @property string|null $end_time
 * @property array<string,mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class WorkScheduleAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\WorkScheduleAssignmentFactory> */
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    /**
     * Cache/log tag név.
     */
    private const TAG = 'work_schedule_assignments';

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'work_schedule_id',
        'employee_id',
        'work_shift_id',
        'day',
        'start_time',
        'end_time',
        'meta',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'company_id' => 'int',
        'work_schedule_id' => 'int',
        'employee_id' => 'int',
        'work_shift_id' => 'int',
        'day' => 'date:Y-m-d',
        'start_time' => 'string',
        'end_time' => 'string',
        'meta' => 'array',
    ];

    /** @var array<int,string> */
    public const SORTABLE = ['id', 'day', 'created_at', 'employee_id', 'work_shift_id'];

    /**
     * Cache tag lekérése.
     */
    public static function getTag(): string
    {
        return self::TAG;
    }

    #[Override]
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(self::TAG)
            ->logFillable()
            ->logOnlyDirty();
    }

    /**
     * Cég kapcsolat.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Munkabeosztás kapcsolat.
     *
     * @return BelongsTo<WorkSchedule, $this>
     */
    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    /**
     * Műszak kapcsolat.
     *
     * @return BelongsTo<WorkShift, $this>
     */
    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    /**
     * Dolgozó kapcsolat.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

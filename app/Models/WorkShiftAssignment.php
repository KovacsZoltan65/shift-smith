<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Műszak hozzárendelés model osztály
 * 
 * Dolgozók műszakokhoz rendelése adott napokra.
 * Kapcsolódik céghez, műszakhoz és dolgozóhoz.
 * 
 * @property int $id
 * @property int $company_id
 * @property int $work_shift_id
 * @property int $employee_id
 * @property string $day Nap (Y-m-d)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WorkShiftAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\WorkShiftAssignmentFactory> */
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    /**
     * Tömegesen tölthető mezők
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'work_shift_id',
        'employee_id',
        'day',
        // 'start_time',
        // 'end_time',
        // 'meta',
    ];

    /**
     * Típuskonverziók
     *
     * @var array<string, string>
     */
    protected $casts = [
        'day' => 'date:Y-m-d',
        'active' => 'bool',
        // 'meta' => 'array',
    ];

    #[Override]
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('work_shift_assignments')
            ->logFillable()
            ->logOnlyDirty();
    }

    /**
     * Cég kapcsolat
     * 
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Műszak kapcsolat
     * 
     * @return BelongsTo<WorkShift, $this>
     */
    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    /**
     * Dolgozó kapcsolat
     * 
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

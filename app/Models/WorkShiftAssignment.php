<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Műszak hozzárendelés model osztály
 * 
 * Dolgozók műszakokhoz rendelése adott napokra.
 * Kapcsolódik céghez, műszakhoz és dolgozóhoz.
 * 
 * @property int $id
 * @property int $company_id
 * @property int $work_schedule_id
 * @property int $work_shift_id
 * @property int $employee_id
 * @property string $date Nap (Y-m-d)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WorkShiftAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\WorkShiftAssignmentFactory> */
    use HasFactory;

    /**
     * Tömegesen tölthető mezők
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'work_schedule_id',
        'work_shift_id',
        'employee_id',
        'date',
    ];

    /**
     * Típuskonverziók
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

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
     * Beosztás kapcsolat
     *
     * @return BelongsTo<WorkSchedule, $this>
     */
    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkShiftAssignment extends Model
{
    use HasFactory;

    /**
     * Tömegesen tölthető mezők
     *
     * @var array<int, string>
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
        // 'meta' => 'array',
    ];

    /**
     * Company kapcsolat
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * WorkShift kapcsolat
     */
    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    /**
     * Employee kapcsolat
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

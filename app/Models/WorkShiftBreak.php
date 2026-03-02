<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkShiftBreak extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'work_shift_id',
        'break_start_time',
        'break_end_time',
        'break_minutes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'company_id' => 'int',
        'work_shift_id' => 'int',
        'break_start_time' => 'string',
        'break_end_time' => 'string',
        'break_minutes' => 'int',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<WorkShift, $this>
     */
    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }
}

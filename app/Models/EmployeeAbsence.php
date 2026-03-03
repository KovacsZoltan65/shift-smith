<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAbsence extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'sick_leave_category_id',
        'date_from',
        'date_to',
        'minutes_per_day',
        'total_minutes',
        'note',
        'status',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'int',
        'employee_id' => 'int',
        'leave_type_id' => 'int',
        'sick_leave_category_id' => 'int',
        'minutes_per_day' => 'int',
        'total_minutes' => 'int',
        'created_by' => 'int',
        'date_from' => 'date',
        'date_to' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function scopeInCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function sickLeaveCategory(): BelongsTo
    {
        return $this->belongsTo(SickLeaveCategory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

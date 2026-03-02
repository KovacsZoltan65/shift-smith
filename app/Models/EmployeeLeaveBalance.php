<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveBalance extends Model
{
    use HasFactory;

    protected $table = 'employee_leave_balances';

    protected $fillable = [
        'employee_id',
        'company_id',
        'year',
        'leave_type',
        'employment_start_date',
        'total_minutes',
        'used_minutes',
        'remaining_minutes',
        'carried_over_minutes',
        'carryover_valid_until',
        'rule_applied',
        'has_employer_exception',
        'employee_blocked_periods',
        'agreement_age_bonus_transfer',
    ];

    protected $casts = [
        'employee_id' => 'int',
        'company_id' => 'int',
        'year' => 'int',
        'employment_start_date' => 'date',
        'total_minutes' => 'int',
        'used_minutes' => 'int',
        'remaining_minutes' => 'int',
        'carried_over_minutes' => 'int',
        'carryover_valid_until' => 'date',
        'has_employer_exception' => 'bool',
        'employee_blocked_periods' => 'array',
        'agreement_age_bonus_transfer' => 'bool',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

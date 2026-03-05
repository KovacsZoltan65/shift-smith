<?php

declare(strict_types=1);

namespace App\Models\Org;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSupervisor extends Model
{
    use HasFactory;

    protected $table = 'employee_supervisors';

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'employee_id',
        'supervisor_employee_id',
        'valid_from',
        'valid_to',
        'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'company_id' => 'int',
        'employee_id' => 'int',
        'supervisor_employee_id' => 'int',
        'created_by_user_id' => 'int',
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function supervisorEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}


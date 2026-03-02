<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyEmployee extends Model
{
    use HasFactory;

    protected $table = 'company_employee';

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'employee_id',
        'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'company_id' => 'int',
        'employee_id' => 'int',
        'active' => 'bool',
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
        return $this->belongsTo(Employee::class);
    }
}

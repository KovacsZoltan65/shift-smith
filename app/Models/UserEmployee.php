<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmployee extends Model
{
    use HasFactory;

    protected $table = 'user_employee';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'company_id',
        'employee_id',
        'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'user_id' => 'int',
        'company_id' => 'int',
        'employee_id' => 'int',
        'active' => 'bool',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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

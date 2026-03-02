<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeProfile extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeProfileFactory> */
    use HasFactory;

    protected $table = 'employee_profiles';

    protected $fillable = [
        'company_id',
        'employee_id',
        'children_count',
        'disabled_children_count',
        'is_disabled',
    ];

    protected $casts = [
        'company_id' => 'int',
        'employee_id' => 'int',
        'children_count' => 'int',
        'disabled_children_count' => 'int',
        'is_disabled' => 'bool',
    ];

    protected static function newFactory(): EmployeeProfileFactory
    {
        return EmployeeProfileFactory::new();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

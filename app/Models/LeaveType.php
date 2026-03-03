<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CATEGORY_LEAVE = 'leave';
    public const CATEGORY_SICK_LEAVE = 'sick_leave';
    public const CATEGORY_PAID_ABSENCE = 'paid_absence';
    public const CATEGORY_UNPAID_ABSENCE = 'unpaid_absence';

    public const CATEGORIES = [
        self::CATEGORY_LEAVE,
        self::CATEGORY_SICK_LEAVE,
        self::CATEGORY_PAID_ABSENCE,
        self::CATEGORY_UNPAID_ABSENCE,
    ];

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'category',
        'affects_leave_balance',
        'requires_approval',
        'active',
    ];

    protected $casts = [
        'company_id' => 'int',
        'affects_leave_balance' => 'bool',
        'requires_approval' => 'bool',
        'active' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public const SORTABLE = [
        'code',
        'name',
        'category',
        'active',
        'updated_at',
        'created_at',
    ];

    public static function getSortable(): array
    {
        return self::SORTABLE;
    }

    public static function getCategories(): array
    {
        return self::CATEGORIES;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeInCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = is_string($term) ? trim($term) : '';

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term): void {
            $builder->where('code', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%");
        });
    }
}

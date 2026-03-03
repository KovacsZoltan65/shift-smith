<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const SORTABLE = [
        'code',
        'name',
        'active',
        'order_index',
        'updated_at',
        'created_at',
    ];

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'active',
        'order_index',
    ];

    protected $casts = [
        'company_id' => 'int',
        'active' => 'bool',
        'order_index' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public static function getSortable(): array
    {
        return self::SORTABLE;
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
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

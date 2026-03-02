<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserSetting extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'user_settings';

    protected $fillable = [
        'user_id',
        'company_id',
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'user_id' => 'int',
        'company_id' => 'int',
        'updated_by' => 'int',
        'type' => 'string',
        'group' => 'string',
        'label' => 'string',
        'description' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function scopeInScope(Builder $query, int $companyId, int $userId): Builder
    {
        return $query->where('company_id', $companyId)->where('user_id', $userId);
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            get: static function (mixed $value): mixed {
                if ($value === null || $value === '') {
                    return null;
                }
                if (is_array($value)) {
                    return $value;
                }
                if (!is_string($value)) {
                    return $value;
                }
                $decoded = json_decode($value, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            },
            set: static fn (mixed $value): ?string => $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}

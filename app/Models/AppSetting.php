<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class AppSetting extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    protected $table = 'app_settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
    ];

    protected $casts = [
        'key' => 'string',
        'type' => 'string',
        'group' => 'string',
        'label' => 'string',
        'description' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const SORTABLE = [
        'key',
        'group',
        'type',
        'updated_at',
        'created_at',
    ];

    public static function getTag(): string
    {
        return 'app_settings';
    }

    public static function getSortable(): array
    {
        return self::SORTABLE;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = is_string($term) ? trim($term) : '';

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term): void {
            $builder->where('key', 'like', "%{$term}%")
                ->orWhere('label', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            get: static function (?string $value): mixed {
                if ($value === null || $value === '') {
                    return null;
                }

                $decoded = json_decode($value, true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            },
            set: static fn (mixed $value): ?string => $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}

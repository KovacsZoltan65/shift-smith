<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanySetting extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'company_settings';

    protected $fillable = [
        'company_id',
        'key',
        'value',
        'updated_by',
    ];

    protected $casts = [
        'company_id' => 'int',
        'updated_by' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

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

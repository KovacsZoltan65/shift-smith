<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Position extends Model
{
    /** @use HasFactory<\Database\Factories\PositionFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    /** @var array<int,string> */
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'active',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'company_id' => 'int',
        'active' => 'bool',
    ];

    /** @var array<int,string> */
    public const SORTABLE = [
        'id',
        'company_id',
        'name',
        'active',
        'created_at',
    ];

    /** @var array<int,string> */
    protected static array $logAttributes = ['*'];

    protected static bool $logOnlyDirty = true;

    protected static string $logName = 'positions';

    /** @var array<int,string> */
    protected static array $recordEvents = ['created', 'updated', 'deleted'];

    public static function getTag(): string
    {
        return static::$logName;
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    #[Override]
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }
}

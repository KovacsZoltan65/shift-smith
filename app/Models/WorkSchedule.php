<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int|string $id
 * @property int|string $company_id
 * @property string $name
 * @property string $date_from
 * @property string $date_to
 * @property string $status
 * @property string|null $notes
 */
class WorkSchedule extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'name',
        'date_from',
        'date_to',
        'status',
        'notes',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'company_id' => 'int',
        'date_from'  => 'date:Y-m-d',
        'date_to'    => 'date:Y-m-d',
        'status'     => 'string',
        'notes'      => 'string',
    ];

    /** @var array<int,string> */
    public const SORTABLE = ['id', 'company_id', 'name', 'date_from', 'date_to', 'status', 'created_at'];

    /*
     * ========================= LOGOLÁS =========================
     */
    /** @var array<int,string> */
    protected static array $logAttributes = ['*'];

    protected static bool $logOnlyDirty = true;

    protected static string $logName = 'work_schedules';

    /** @var array<int,string> */
    protected static array $recordEvents = ['created', 'updated', 'deleted'];

    public function getLogNameToUse(string $eventName = ''): string
    {
        return static::$logName ?? 'default';
    }

    public static function getTag(): string
    {
        return static::$logName;
    }

    #[Override]
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable();
    }

    /**
     * ===========================================================
     */

    /** @return array<int,string> */
    public static function getSortable(): array
    {
        return self::SORTABLE;
    }

    /**
     * @param Builder<WorkSchedule> $query
     * @return Builder<WorkSchedule>
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

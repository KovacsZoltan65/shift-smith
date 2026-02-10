<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Override;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use LogsActivity;
    
    protected $fillable = ['name', 'guard_name'];
    
    /** Rendezhető mezők (helperhez / validáláshoz) */
    /** @var array<int,string> */
    protected static array $sortable = ['name', 'guard_name'];
    
    /** (Opcionálisan) típus-casting – a Carbon megtartásához hasznos */
    /** @var array<string,string> */
    protected $casts = [];
    
    /** @var array<int,string> */
    public const SORTABLE = [
        'id', 'name', 'guard_name',
    ];
    
    /*
     * ========================= LOGOLÁS =========================
     */
    /** @var array<int,string> */
    protected static array $logAttributes = ['*'];
    
    protected static bool $logOnlyDirty = true;
    
    protected static string $logName = 'roles';
    
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
            ->logFillable()
            ->dontLogIfAttributesChangedOnly(['remember_token'])
            ->logExcept(['password', 'remember_token'])
            //->dontLogAttributes(['password'])
                ;
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
     * @return array<int, array{id:int, name:string}>
     */
    public static function getToSelect(): array
    {
        return static::query()
            ->select(['id', 'name'])
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])
            ->all();
    }
}

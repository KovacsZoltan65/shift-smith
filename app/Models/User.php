<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;


use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\LogOptions;
use Override;

/**
 * Felhasználó model osztály
 * 
 * Laravel Authenticatable kiterjesztése email verifikációval.
 * Spatie Permission integráció szerepkör és jogosultság kezeléshez.
 * Activity log támogatással minden módosítás naplózásához.
 * 
 * @property int $id
 * @property string $name Felhasználó teljes neve
 * @property string $email Email cím (egyedi)
 * @property string $password Hashelve tárolt jelszó
 * @property \Illuminate\Support\Carbon|null $email_verified_at Email megerősítés időpontja
 * @property string|null $remember_token Bejelentkezve maradás token
 * @property \Illuminate\Support\Carbon $created_at Létrehozás időpontja
 * @property \Illuminate\Support\Carbon $updated_at Módosítás időpontja
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /** @var array<int,string> */
    protected static array $sortable = ['id', 'name', 'email'];
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    
    /**
     * Felhasználó összes jogosultságának lekérése map formátumban
     * 
     * Kulcs: permission név, érték: true (frontend gyors lookuphoz).
     * Spatie Permission trait getAllPermissions() metódusát használja.
     *
     * @return Collection<string,bool> Jogosultság név => true párok
     */
    public function getPermissionArray(): Collection
    {
        /** @var Collection<int,string> $names */
        $names = $this->getAllPermissions()->pluck('name');

        /** @var Collection<string,bool> $map */
        $map = $names->mapWithKeys(fn (string $name): array => [$name => true]);

        return $map;
    }
    
    /*
     * ========================= LOGOLÁS =========================
     */
    /** @var array<int,string> */
    protected static array $logAttributes = ['name', 'email'];
    
    /** @var array<int,string> */
    protected static array $logAttributesToIgnore = ['password', 'remember_token'];
    
    protected static bool $logOnlyDirty = true;
    
    protected static string $logName = 'users';
    
    /** @var array<int,string> */
    protected static array $recordEvents = ['created', 'updated', 'deleted'];
    
    /**
     * Activity log név lekérése
     * 
     * @param string $eventName Esemény neve (created, updated, deleted)
     * @return string Log csatorna neve
     */
    public function getLogNameToUse(string $eventName = ''): string
    {
        return static::$logName ?? 'default';
    }

    /**
     * Cache tag név lekérése
     * 
     * @return string Cache tag azonosító
     */
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
    
    /**
     * Rendezhető mezők listája
     * 
     * @return array<int,string> Rendezhető oszlopnevek
     */
    public static function getSortable(): array
    {
        return self::$sortable;
    }

    /**
     * Aktív felhasználók szűrése
     * 
     * @param  Builder<User>  $query Query builder
     * @return Builder<User> Szűrt query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', APP_ACTIVE);
    }

    /**
     * Keresés név és email mezőkben
     * 
     * Request paraméterek alapján szűr név és email mezőkre.
     * 
     * @param  Builder<self>  $query Query builder
     * @param  Request  $request HTTP kérés objektum
     * @return Builder<self> Szűrt query
     */
    public function scopeSearch(Builder $query, Request $request): Builder
    {
        return $query->when($request->input('search'), function(Builder $q) use ($request): void {
            $q->when($request->input('name'), fn (Builder $qq): Builder => $qq->where('name', 'like', "%{$request->input('name')}%"));

            $q->when($request->input('email'), fn (Builder $qq): Builder => $qq->where('email', 'like', "%{$request->input('email')}%"));
        });
    }

    /**
     * A felhasználóhoz rendelt cégek.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withTimestamps();
    }

    /**
     * A felhasználóhoz rendelt dolgozók.
     *
     * @return BelongsToMany<Employee, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'user_employee')
            ->withPivot(['active'])
            ->withTimestamps();
    }
}

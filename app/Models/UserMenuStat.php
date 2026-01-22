<?php

namespace App\Models;

use DateTime;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $menu_key
 * @property int $hit_count
 * @property DateTime $last_used_at
 * 
 */
class UserMenuStat extends Model
{
    protected $table = 'user_menu_stats';

    protected $fillable = [
        'user_id',
        'menu_key',
        'hit_count',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];
}

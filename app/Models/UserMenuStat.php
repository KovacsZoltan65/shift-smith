<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

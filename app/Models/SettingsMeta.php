<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettingsMeta extends Model
{
    use HasFactory;

    protected $table = 'settings_meta';

    protected $fillable = [
        'key',
        'group',
        'label',
        'type',
        'default_value',
        'description',
        'options',
        'validation',
        'order_index',
        'is_editable',
        'is_visible',
    ];

    protected $casts = [
        'default_value' => 'array',
        'options' => 'array',
        'validation' => 'array',
        'order_index' => 'int',
        'is_editable' => 'bool',
        'is_visible' => 'bool',
    ];
}


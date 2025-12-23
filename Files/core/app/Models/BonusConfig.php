<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusConfig extends Model
{
    protected $fillable = [
        'version_code',
        'config_json',
        'is_active',
        'created_by',
        'activated_at',
    ];

    protected $casts = [
        'config_json' => 'array',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
    ];
}

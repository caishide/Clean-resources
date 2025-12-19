<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'admin_id',
        'action_type',
        'entity_type',
        'entity_id',
        'meta',
    ];
    protected $casts = [
        'meta' => 'array',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}

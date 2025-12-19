<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WithdrawMethod extends Model
{
    use GlobalStatus, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'currency',
        'min_limit',
        'max_limit',
        'fixed_charge',
        'percent_charge',
        'rate',
        'status',
    ];

    protected $casts = [
        'user_data' => 'object',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}

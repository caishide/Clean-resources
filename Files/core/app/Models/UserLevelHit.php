<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserLevelHit extends Model {
    protected $fillable = ["user_id", "level", "position", "amount", "first_hit_at", "order_trx", "bonus_amount", "rewarded"];
    public function user() { return $this->belongsTo(User::class); }
}

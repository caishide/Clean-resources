<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PendingBonus extends Model {
    protected $fillable = ["recipient_id", "bonus_type", "amount", "source_type", "source_id", "accrued_week_key", "status", "release_mode", "released_trx"];
    public function recipient() { return $this->belongsTo(User::class, "recipient_id"); }
}

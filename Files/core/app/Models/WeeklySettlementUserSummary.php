<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class WeeklySettlementUserSummary extends Model {
    protected $fillable = ["settlement_id", "user_id", "bv_left", "bv_right", "weak_pv", "pair_capped_potential", "matching_potential", "actual_pair_bonus", "actual_matching_bonus"];
    public function settlement() { return $this->belongsTo(WeeklySettlement::class, "settlement_id"); }
    public function user() { return $this->belongsTo(User::class); }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserPointsLog extends Model {
    protected $table = "user_points_log";
    protected $fillable = ["user_id", "source_type", "source_id", "points", "description", "adjustment_batch_id", "reversal_of_id"];
    public function user() { return $this->belongsTo(User::class); }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PvLedger extends Model {
    use HasFactory;

    protected $table = "pv_ledger";
    protected $fillable = ["user_id", "from_user_id", "position", "level", "amount", "trx_type", "source_type", "source_id", "reversal_of_id", "adjustment_batch_id", "details"];
    public function user() { return $this->belongsTo(User::class); }
}

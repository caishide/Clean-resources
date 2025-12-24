<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class WeeklySettlement extends Model {
    protected $fillable = ["week_key", "status", "total_pv", "global_reserve", "fixed_sales", "variable_potential", "k_factor", "dry_run", "started_at", "completed_at", "error_message", "carry_flash_at"];
    protected $casts = ["dry_run" => "boolean", "started_at" => "datetime", "completed_at" => "datetime", "carry_flash_at" => "datetime"];
}

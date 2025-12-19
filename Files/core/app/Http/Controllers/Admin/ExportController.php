<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WeeklySettlementUserSummary;
use App\Models\WeeklySettlement;
use App\Models\QuarterlySettlement;
use App\Models\DividendLog;
use App\Models\PendingBonus;
use App\Models\PvLedger;
use App\Models\UserPointsLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function weekly(Request $request): StreamedResponse
    {
        $week = $request->input('week') ?: WeeklySettlement::orderByDesc('week_key')->value('week_key');
        $rows = WeeklySettlementUserSummary::where('week_key', $week)->with('user:id,username')->get();

        $headers = ['week_key','user_id','username','left_pv_initial','right_pv_initial','left_pv_end','right_pv_end','pair_count','pair_theoretical','pair_capped_potential','pair_paid','matching_potential','matching_paid','cap_amount','cap_used','k_factor'];
        return $this->csv("weekly_{$week}.csv", $headers, $rows->map(function($r){
            return [
                $r->week_key,
                $r->user_id,
                optional($r->user)->username,
                $r->left_pv_initial,
                $r->right_pv_initial,
                $r->left_pv_end,
                $r->right_pv_end,
                $r->pair_count,
                $r->pair_theoretical,
                $r->pair_capped_potential,
                $r->pair_paid,
                $r->matching_potential,
                $r->matching_paid,
                $r->cap_amount,
                $r->cap_used,
                $r->k_factor,
            ];
        }));
    }

    public function quarterly(Request $request): StreamedResponse
    {
        $quarter = $request->input('quarter') ?: QuarterlySettlement::orderByDesc('quarter_key')->value('quarter_key');
        $rows = DividendLog::where('quarter_key', $quarter)->with('user:id,username')->get();
        $headers = ['quarter_key','user_id','username','pool_type','shares','score','dividend_amount','status','reason'];
        return $this->csv("quarterly_{$quarter}.csv", $headers, $rows->map(function($r){
            return [
                $r->quarter_key,
                $r->user_id,
                optional($r->user)->username,
                $r->pool_type,
                $r->shares,
                $r->score,
                $r->dividend_amount,
                $r->status,
                $r->reason,
            ];
        }));
    }

    public function pendingBonuses(Request $request): StreamedResponse
    {
        $rows = PendingBonus::with('recipient:id,username')->orderByDesc('id')->get();
        $headers = ['id','recipient_id','username','bonus_type','amount','source_type','source_id','accrued_week_key','status','release_mode','released_trx','created_at'];
        return $this->csv('pending_bonuses.csv', $headers, $rows->map(function($r){
            return [
                $r->id,
                $r->recipient_id,
                optional($r->recipient)->username,
                $r->bonus_type,
                $r->amount,
                $r->source_type,
                $r->source_id,
                $r->accrued_week_key,
                $r->status,
                $r->release_mode,
                $r->released_trx,
                $r->created_at,
            ];
        }));
    }

    public function pvLedger(Request $request): StreamedResponse
    {
        $sourceType = $request->input('source_type');
        $rows = PvLedger::with('user:id,username')
            ->when($sourceType, fn($q) => $q->where('source_type', $sourceType))
            ->orderByDesc('id')->limit(5000)->get();
        $headers = ['id','user_id','username','from_user_id','position','level','amount','trx_type','source_type','source_id','adjustment_batch_id','reversal_of_id','created_at'];
        return $this->csv('pv_ledger.csv', $headers, $rows->map(function($r){
            return [
                $r->id,
                $r->user_id,
                optional($r->user)->username,
                $r->from_user_id,
                $r->position,
                $r->level,
                $r->amount,
                $r->trx_type,
                $r->source_type,
                $r->source_id,
                $r->adjustment_batch_id,
                $r->reversal_of_id,
                $r->created_at,
            ];
        }));
    }

    public function points(Request $request): StreamedResponse
    {
        $rows = UserPointsLog::with('user:id,username')->orderByDesc('id')->limit(5000)->get();
        $headers = ['id','user_id','username','source_type','source_id','points','description','adjustment_batch_id','reversal_of_id','created_at'];
        return $this->csv('points_logs.csv', $headers, $rows->map(function($r){
            return [
                $r->id,
                $r->user_id,
                optional($r->user)->username,
                $r->source_type,
                $r->source_id,
                $r->points,
                $r->description,
                $r->adjustment_batch_id,
                $r->reversal_of_id,
                $r->created_at,
            ];
        }));
    }

    private function csv(string $filename, array $headers, $rows): StreamedResponse
    {
        return response()->streamDownload(function() use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

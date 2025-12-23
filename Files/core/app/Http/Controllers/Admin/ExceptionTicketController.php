<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdjustmentBatch;
use App\Models\PendingBonus;
use App\Models\PvLedger;
use App\Models\QuarterlySettlement;
use App\Models\SupportTicket;
use App\Models\UserExtra;
use App\Models\WeeklySettlement;
use Illuminate\Support\Facades\DB;

class ExceptionTicketController extends Controller
{
    public function index()
    {
        $pageTitle = '异常与工单';

        $pendingAdjustments = AdjustmentBatch::whereNull('finalized_at')->count();
        $pendingBonuses = PendingBonus::where('status', 'pending')->count();
        $pendingTickets = SupportTicket::pending()->count();

        $negativePvUsers = UserExtra::where('bv_left', '<', 0)
            ->orWhere('bv_right', '<', 0)
            ->count();

        $duplicatePvGroups = PvLedger::select(
            'source_type',
            'source_id',
            'user_id',
            'position',
            'trx_type',
            DB::raw('COUNT(*) as duplicate_count')
        )
            ->groupBy('source_type', 'source_id', 'user_id', 'position', 'trx_type')
            ->having('duplicate_count', '>', 1)
            ->get()
            ->count();

        $weeklyDuplicateCount = WeeklySettlement::select('week_key', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('week_key')
            ->having('duplicate_count', '>', 1)
            ->get()
            ->count();

        $quarterlyDuplicateCount = QuarterlySettlement::select('quarter_key', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('quarter_key')
            ->having('duplicate_count', '>', 1)
            ->get()
            ->count();

        $weeklyMissingSummaryCount = WeeklySettlement::leftJoin('weekly_settlement_user_summaries', 'weekly_settlements.week_key', '=', 'weekly_settlement_user_summaries.week_key')
            ->select('weekly_settlements.id', DB::raw('COUNT(weekly_settlement_user_summaries.id) as summary_count'))
            ->groupBy('weekly_settlements.id')
            ->having('summary_count', '=', 0)
            ->get()
            ->count();

        return view('admin.exceptions.index', compact(
            'pageTitle',
            'pendingAdjustments',
            'pendingBonuses',
            'pendingTickets',
            'negativePvUsers',
            'duplicatePvGroups',
            'weeklyDuplicateCount',
            'quarterlyDuplicateCount',
            'weeklyMissingSummaryCount'
        ));
    }

    public function pv()
    {
        $pageTitle = 'PV 异常';

        $negativeUsers = UserExtra::query()
            ->select('user_extras.*', 'users.username', 'users.email')
            ->join('users', 'users.id', '=', 'user_extras.user_id')
            ->where(function ($query) {
                $query->where('bv_left', '<', 0)
                    ->orWhere('bv_right', '<', 0);
            })
            ->orderByDesc('user_extras.id')
            ->paginate(20, ['*'], 'neg_page');

        $duplicatePvGroups = PvLedger::query()
            ->select(
                'source_type',
                'source_id',
                'user_id',
                'position',
                'trx_type',
                DB::raw('COUNT(*) as duplicate_count')
            )
            ->groupBy('source_type', 'source_id', 'user_id', 'position', 'trx_type')
            ->having('duplicate_count', '>', 1)
            ->orderByDesc('duplicate_count')
            ->paginate(20, ['*'], 'dup_page');

        return view('admin.exceptions.pv', compact('pageTitle', 'negativeUsers', 'duplicatePvGroups'));
    }

    public function settlements()
    {
        $pageTitle = '结算异常';

        $weeklyDuplicates = WeeklySettlement::query()
            ->select(
                'week_key',
                DB::raw('COUNT(*) as duplicate_count'),
                DB::raw('GROUP_CONCAT(id ORDER BY id DESC) as ids')
            )
            ->groupBy('week_key')
            ->having('duplicate_count', '>', 1)
            ->orderByDesc('duplicate_count')
            ->paginate(20, ['*'], 'weekly_page');

        $quarterlyDuplicates = QuarterlySettlement::query()
            ->select(
                'quarter_key',
                DB::raw('COUNT(*) as duplicate_count'),
                DB::raw('GROUP_CONCAT(id ORDER BY id DESC) as ids')
            )
            ->groupBy('quarter_key')
            ->having('duplicate_count', '>', 1)
            ->orderByDesc('duplicate_count')
            ->paginate(20, ['*'], 'quarterly_page');

        $weeklyMissingSummaries = WeeklySettlement::leftJoin('weekly_settlement_user_summaries', 'weekly_settlements.week_key', '=', 'weekly_settlement_user_summaries.week_key')
            ->select(
                'weekly_settlements.id',
                'weekly_settlements.week_key',
                'weekly_settlements.total_pv',
                DB::raw('COUNT(weekly_settlement_user_summaries.id) as summary_count')
            )
            ->groupBy('weekly_settlements.id', 'weekly_settlements.week_key', 'weekly_settlements.total_pv')
            ->having('summary_count', '=', 0)
            ->orderByDesc('weekly_settlements.id')
            ->paginate(20, ['*'], 'missing_page');

        return view('admin.exceptions.settlements', compact(
            'pageTitle',
            'weeklyDuplicates',
            'quarterlyDuplicates',
            'weeklyMissingSummaries'
        ));
    }
}

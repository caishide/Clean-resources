<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WeeklySettlement;
use App\Models\QuarterlySettlement;
use App\Services\SettlementService;
use Illuminate\Http\Request;

class SettlementManagementController extends Controller
{
    public function weekly(Request $request)
    {
        $pageTitle = '周结算管理';
        $settlements = WeeklySettlement::orderByDesc('week_key')->paginate(getPaginate());
        $week = $request->input('week', now()->subWeek()->format('o-\\WW'));
        $preview = null;

        return view('admin.settlements.weekly', compact('pageTitle', 'settlements', 'week', 'preview'));
    }

    public function weeklyPreview(Request $request, SettlementService $service)
    {
        $pageTitle = '周结算管理';
        $week = $request->input('week', now()->subWeek()->format('o-\\WW'));
        $preview = $service->executeWeeklySettlement($week, true, true);
        $settlements = WeeklySettlement::orderByDesc('week_key')->paginate(getPaginate());

        return view('admin.settlements.weekly', compact('pageTitle', 'settlements', 'week', 'preview'));
    }

    public function weeklyExecute(Request $request, SettlementService $service)
    {
        $week = $request->input('week', now()->subWeek()->format('o-\\WW'));
        try {
            $result = $service->executeWeeklySettlement($week, false);
            $notify[] = ['success', '结算完成'];
            return back()->withNotify($notify)->with('execute_result', $result);
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function quarterly(Request $request)
    {
        $pageTitle = '季度分红管理';
        $settlements = QuarterlySettlement::orderByDesc('quarter_key')->paginate(getPaginate());
        $quarter = $request->input('quarter', now()->subQuarter()->format('Y') . '-Q' . now()->subQuarter()->quarter);
        $preview = null;

        return view('admin.settlements.quarterly', compact('pageTitle', 'settlements', 'quarter', 'preview'));
    }

    public function quarterlyPreview(Request $request, SettlementService $service)
    {
        $pageTitle = '季度分红管理';
        $quarter = $request->input('quarter', now()->subQuarter()->format('Y') . '-Q' . now()->subQuarter()->quarter);
        $preview = $service->executeQuarterlySettlement($quarter, true);
        $settlements = QuarterlySettlement::orderByDesc('quarter_key')->paginate(getPaginate());

        return view('admin.settlements.quarterly', compact('pageTitle', 'settlements', 'quarter', 'preview'));
    }

    public function quarterlyExecute(Request $request, SettlementService $service)
    {
        $quarter = $request->input('quarter', now()->subQuarter()->format('Y') . '-Q' . now()->subQuarter()->quarter);
        try {
            $result = $service->executeQuarterlySettlement($quarter, false);
            $notify[] = ['success', '季度分红结算完成'];
            return back()->withNotify($notify)->with('execute_result', $result);
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }
}

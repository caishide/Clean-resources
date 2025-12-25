<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WeeklySettlement;
use App\Models\QuarterlySettlement;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RiskReportController extends Controller
{
    public function kFactorHistory(Request $request)
    {
        $pageTitle = "K值历史报表";

        $weeklySettlements = WeeklySettlement::select(
            "week_key",
            "total_pv",
            "k_factor",
            "total_bonus",
            "global_reserve",
            "finalized_at"
        )
            ->orderByDesc("week_key")
            ->paginate(50);

        $quarterlySettlements = QuarterlySettlement::select(
            "quarter_key",
            "total_pv",
            "k_factor",
            "huchi_pool_amount",
            "linghang_pool_amount",
            "finalized_at"
        )
            ->orderByDesc("quarter_key")
            ->paginate(50, ["*"], "quarter_page");

        $stats = [
            "avg_weekly_k" => WeeklySettlement::avg("k_factor") ?? 0,
            "min_weekly_k" => WeeklySettlement::min("k_factor") ?? 0,
            "max_weekly_k" => WeeklySettlement::max("k_factor") ?? 0,
            "total_weeks" => WeeklySettlement::count(),
            "total_quarters" => QuarterlySettlement::count(),
        ];

        $trendData = WeeklySettlement::orderBy("week_key")
            ->take(12)
            ->pluck("k_factor")
            ->reverse()
            ->values();

        return view("admin.reports.k_factor_history", compact(
            "pageTitle",
            "weeklySettlements",
            "quarterlySettlements",
            "stats",
            "trendData"
        ));
    }

    public function exportKFactor(): StreamedResponse
    {
        $rows = WeeklySettlement::orderBy("week_key")->get();

        $headers = ["week_key", "total_pv", "k_factor", "total_bonus", "global_reserve", "finalized_at"];

        return response()->streamDownload(function() use ($headers, $rows) {
            $out = fopen("php://output", "w");
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->week_key,
                    $r->total_pv,
                    $r->k_factor,
                    $r->total_bonus,
                    $r->global_reserve,
                    $r->finalized_at,
                ]);
            }
            fclose($out);
        }, "k_factor_history_" . date("Ymd") . ".csv", ["Content-Type" => "text/csv"]);
    }

    public function settlementAnomaly(Request $request)
    {
        $pageTitle = "结算异常分析";

        $anomalies = WeeklySettlement::where(function ($query) {
                $query->where("k_factor", "<", 0.5)
                    ->orWhere("k_factor", ">", 1.5);
            })
            ->orderBy("k_factor")
            ->paginate(getPaginate());

        return view("admin.reports.settlement_anomaly", compact("pageTitle", "anomalies"));
    }

    public function dashboard(Request $request)
    {
        $pageTitle = "风控仪表盘";

        $latestK = WeeklySettlement::orderByDesc("week_key")->value("k_factor");

        $kTrend = WeeklySettlement::orderByDesc("week_key")
            ->take(4)
            ->pluck("k_factor")
            ->reverse()
            ->values();

        $kVolatility = WeeklySettlement::selectRaw("STDDEV(k_factor) as std_dev, AVG(k_factor) as avg, COUNT(*) as count")->first();

        $highRiskUsers = [];

        return view("admin.reports.risk_dashboard", compact(
            "pageTitle",
            "latestK",
            "kTrend",
            "kVolatility",
            "highRiskUsers"
        ));
    }
}

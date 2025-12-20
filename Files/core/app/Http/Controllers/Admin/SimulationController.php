<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SimulationService;
use Illuminate\Http\Request;

class SimulationController extends Controller
{
    public function index(Request $request, SimulationService $simulationService)
    {
        $pageTitle = '结算模拟';
        $sessionKey = 'admin_simulation_results';

        $defaults = [
            'weeks' => 4,
            'left_ratio' => 60,
            'orders_per_week' => 200,
            'user_order_ratio' => 0.9,
            'unpaid_new_per_week' => 0,
            'engine' => 'fast',
        ];

        $params = array_merge($defaults, $request->all());
        $results = null;

        if ($request->isMethod('post') || $request->boolean('run')) {
            @ini_set('memory_limit', '512M');
            @set_time_limit(0);
            $engine = strtolower((string) ($params['engine'] ?? ''));
            if ($engine === '') {
                $engine = 'fast';
                $params['engine'] = 'fast';
            }
            $params['persist'] = false;

            try {
                if ($engine === 'db') {
                    $weeks = (int) ($params['weeks'] ?? 0);
                    $ordersPerWeek = (int) ($params['orders_per_week'] ?? 0);
                    $ratio = (float) ($params['user_order_ratio'] ?? 0.9);
                    $ratio = $ratio > 0 ? $ratio : 0.9;
                    $weeklyNewEstimate = (int) max(1, ceil($ordersPerWeek * $ratio));
                    $estimatedUsers = $weeklyNewEstimate * max(1, $weeks);
                    $estimated = ($weeks * $ordersPerWeek) + $estimatedUsers;
                    if ($weeks > 4 || $ordersPerWeek > 80 || $estimated > 500) {
                        throw new \RuntimeException('全链路模式（db）较慢：建议 weeks<=4、orders_per_week<=80。更大规模请用“快速（纯计算）”模式。');
                    }
                }
                $results = $simulationService->simulate($params);
                $results = $this->ensureSummary($results);
                $request->session()->put($sessionKey, $results);
            } catch (\Throwable $e) {
                $notify[] = ['error', $e->getMessage()];
                return back()->withNotify($notify)->withInput();
            }
        } else {
            $results = $request->session()->get($sessionKey);
        }

        return view('admin.simulation.index', [
            'pageTitle' => $pageTitle,
            'params' => $params,
            'results' => $results,
        ]);
    }

    private function ensureSummary(array $results): array
    {
        if (!empty($results['summary'])) {
            return $results;
        }

        $weeks = $results['weeks'] ?? [];
        if (empty($weeks)) {
            return $results;
        }

        $weeksCount = count($weeks);
        $kSum = array_sum(array_column($weeks, 'k_factor'));
        $results['summary'] = [
            'weeks' => $weeksCount,
            'months' => count($results['months'] ?? []),
            'quarters' => count($results['quarters'] ?? []),
            'order_pv' => array_sum(array_column($weeks, 'order_pv')),
            'total_pv' => array_sum(array_column($weeks, 'total_pv')),
            'system_pv' => array_sum(array_column($weeks, 'system_pv')),
            'total_cap' => array_sum(array_column($weeks, 'total_cap')),
            'global_reserve' => array_sum(array_column($weeks, 'global_reserve')),
            'fixed_sales' => array_sum(array_column($weeks, 'fixed_sales')),
            'variable_potential' => array_sum(array_column($weeks, 'variable_potential')),
            'remaining' => array_sum(array_column($weeks, 'remaining')),
            'k_factor_avg' => $weeksCount > 0 ? ($kSum / $weeksCount) : 0,
            'direct_paid' => array_sum(array_column($weeks, 'direct_paid')),
            'level_pair_paid' => array_sum(array_column($weeks, 'level_pair_paid')),
            'pair_paid' => array_sum(array_column($weeks, 'pair_paid')),
            'matching_paid' => array_sum(array_column($weeks, 'matching_paid')),
            'pending_count' => array_sum(array_column($weeks, 'pending_count')),
            'pending_amount' => array_sum(array_column($weeks, 'pending_amount')),
        ];

        if (empty($results['total_users'])) {
            $lastWeek = end($weeks);
            $results['total_users'] = $lastWeek['total_users'] ?? 0;
        }

        return $results;
    }
}

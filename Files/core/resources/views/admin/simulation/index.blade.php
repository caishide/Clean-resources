@extends('admin.layouts.app')

@section('panel')
<div class="row gy-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">模拟参数</h5>
                @php
                    $engine = strtolower($params['engine'] ?? 'fast');
                @endphp
                @if($engine === 'db')
                    <span class="badge bg-primary">全链路（自动回滚）</span>
                @else
                    <span class="badge bg-success">快速（纯计算）</span>
                @endif
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('admin.simulation.bonus') }}">
                    <input type="hidden" name="run" value="1">
                    <div class="mb-3">
                        <label class="form-label">周数</label>
                        <input type="number" name="weeks" class="form-control" value="{{ $params['weeks'] ?? 4 }}" min="1" max="260" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">左区比例（%）</label>
                        <input type="number" name="left_ratio" class="form-control" value="{{ $params['left_ratio'] ?? 60 }}" min="0" max="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">每周订单数</label>
                        <input type="number" name="orders_per_week" class="form-control" value="{{ $params['orders_per_week'] ?? 200 }}" min="1" max="20000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">新增用户比例（=订单数×比例）</label>
                        <input type="number" step="0.01" name="user_order_ratio" class="form-control" value="{{ $params['user_order_ratio'] ?? 0.9 }}" min="0.1" max="2" required>
                        <small class="text-muted">示例：0.9 表示每周新增用户=订单数×0.9</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">每周未付款新增用户数</label>
                        <input type="number" name="unpaid_new_per_week" class="form-control" value="{{ $params['unpaid_new_per_week'] ?? 0 }}" min="0" max="20000" required>
                        <small class="text-muted">仅影响新用户激活与奖金是否进入待处理</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">模拟引擎</label>
                        <select name="engine" class="form-control">
                            <option value="fast" {{ ($params['engine'] ?? 'fast') == 'fast' ? 'selected' : '' }}>快速（推荐，纯计算）</option>
                            <option value="db" {{ ($params['engine'] ?? '') == 'db' ? 'selected' : '' }}>全链路（写订单/发货/结算，慢）</option>
                        </select>
                        <small class="text-muted">如果遇到 502/超时，请使用“快速”模式</small>
                    </div>
                    <button type="submit" class="btn btn--primary w-100">运行模拟</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        @php
            $summary = [];
            if (!empty($results) && is_array($results)) {
                $summary = is_array($results['summary'] ?? null) ? $results['summary'] : [];

                if (!empty($results['weeks'])) {
                    $weeks = $results['weeks'];
                    $weeksCount = count($weeks);
                    $kSum = array_sum(array_column($weeks, 'k_factor'));
                    $summaryFromWeeks = [
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

                    $summary = array_replace($summaryFromWeeks, $summary);
                }
            }
        @endphp
        @if($results)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">汇总</h5>
                </div>
                <div class="card-body">
                    @if(empty($summary))
                        <div class="text-muted">汇总未生成，请检查是否有周度数据。</div>
                    @else
                        @php
                            $totalOrders = !empty($results['weeks']) ? array_sum(array_column($results['weeks'], 'orders')) : 0;
                            $orderPV = (float) ($summary['order_pv'] ?? 0);
                            if ($orderPV <= 0) {
                                $orderPV = (float) ($summary['total_pv'] ?? 0);
                            }
                            if ($orderPV <= 0 && !empty($results['weeks'])) {
                                $weekOrderPv = array_sum(array_column($results['weeks'], 'order_pv'));
                                $orderPV = $weekOrderPv > 0 ? $weekOrderPv : ($totalOrders * 3000);
                            }
                            $systemPV = (float) ($summary['system_pv'] ?? 0);
                            if ($systemPV <= 0 && !empty($results['weeks'])) {
                                $systemPV = array_sum(array_column($results['weeks'], 'system_pv'));
                            }
                            if ($systemPV <= 0) {
                                $systemPV = (float) ($summary['total_pv'] ?? 0);
                            }
                            $directPaid = (float) ($summary['direct_paid'] ?? 0);
                            $levelPairPaid = (float) ($summary['level_pair_paid'] ?? 0);
                            $pairPaid = (float) ($summary['pair_paid'] ?? 0);
                            $matchingPaid = (float) ($summary['matching_paid'] ?? 0);
                            $pendingAmount = (float) ($summary['pending_amount'] ?? 0);
                            $globalReserve = (float) ($summary['global_reserve'] ?? 0);
                            $totalPaid = $directPaid + $levelPairPaid + $pairPaid + $matchingPaid;
                            $totalBonus = $totalPaid + $pendingAmount;
                            $payoutRatioPaidOrder = $orderPV > 0 ? ($totalPaid / $orderPV) : 0;
                            $payoutRatioAllOrder = $orderPV > 0 ? ($totalBonus / $orderPV) : 0;
                            $reserveRatioOrder = $orderPV > 0 ? ($globalReserve / $orderPV) : 0;
                            $payoutRatioPaidSystem = $systemPV > 0 ? ($totalPaid / $systemPV) : 0;
                            $payoutRatioAllSystem = $systemPV > 0 ? ($totalBonus / $systemPV) : 0;
                            $reserveRatioSystem = $systemPV > 0 ? ($globalReserve / $systemPV) : 0;
                            $totalOut = $totalBonus + $globalReserve;
                            $totalOutRatioOrder = $orderPV > 0 ? ($totalOut / $orderPV) : 0;
                            $totalOutRatioSystem = $systemPV > 0 ? ($totalOut / $systemPV) : 0;
                            $bonusItems = [
                                ['label' => '直推奖', 'amount' => $directPaid],
                                ['label' => '层碰奖', 'amount' => $levelPairPaid],
                                ['label' => '对碰奖', 'amount' => $pairPaid],
                                ['label' => '管理奖', 'amount' => $matchingPaid],
                            ];
                            $totalCap = (float) ($summary['total_cap'] ?? 0);
                            $variablePotential = (float) ($summary['variable_potential'] ?? 0);
                            $remainingPool = (float) ($summary['remaining'] ?? 0);
                            $fixedSales = (float) ($summary['fixed_sales'] ?? 0);
                            $kAvg = (float) ($summary['k_factor_avg'] ?? 0);
                            $variablePaid = $pairPaid + $matchingPaid;
                            $variablePaidRatio = $variablePotential > 0 ? ($variablePaid / $variablePotential) : 0;
                            $poolUsageRatio = $remainingPool > 0 ? ($variablePaid / $remainingPool) : 0;
                        @endphp
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded">
                                    <div class="text-dark fw-bold mb-2">核心指标</div>
                                    <div class="d-flex justify-content-between text-dark"><span>计算周数</span><span>{{ $summary['weeks'] ?? 0 }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>总订单数</span><span>{{ number_format($totalOrders) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>模拟用户数（估算）</span><span>{{ number_format($results['total_users'] ?? 0) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>订单营业额（PV）</span><span>{{ number_format($orderPV, 0) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>系统累计PV（安置链）</span><span>{{ number_format($systemPV, 0) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>总奖金（含待处理）</span><span>{{ number_format($totalBonus, 2) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>已发放奖金</span><span>{{ number_format($totalPaid, 2) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>待处理奖金</span><span>{{ number_format($pendingAmount, 2) }}</span></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded">
                                    <div class="text-dark fw-bold mb-2">拨出比例（订单口径）</div>
                                    <div class="d-flex justify-content-between text-dark"><span>已发放奖金占比</span><span>{{ number_format($payoutRatioPaidOrder * 100, 2) }}%</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>奖金总拨出占比</span><span>{{ number_format($payoutRatioAllOrder * 100, 2) }}%</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>功德池占比</span><span>{{ number_format($reserveRatioOrder * 100, 2) }}%</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>总拨出占比</span><span>{{ number_format($totalOutRatioOrder * 100, 2) }}%</span></div>
                                    <div class="text-dark fw-bold mt-3 mb-2">拨出比例（系统PV口径）</div>
                                    <div class="d-flex justify-content-between text-dark"><span>已发放奖金占比</span><span>{{ number_format($payoutRatioPaidSystem * 100, 2) }}%</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>奖金总拨出占比</span><span>{{ number_format($payoutRatioAllSystem * 100, 2) }}%</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>功德池占比</span><span>{{ number_format($reserveRatioSystem * 100, 2) }}%</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>总拨出占比</span><span>{{ number_format($totalOutRatioSystem * 100, 2) }}%</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>功德池金额</span><span>{{ number_format($globalReserve, 2) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>总拨出金额</span><span>{{ number_format($totalOut, 2) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>K(均值)</span><span>{{ number_format($summary['k_factor_avg'] ?? 0, 4) }}</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <div class="p-3 bg-light rounded">
                                    <div class="text-dark fw-bold mb-2">拨出池拆解（对碰/管理受此约束）</div>
                                    <div class="d-flex justify-content-between text-dark"><span>总拨出上限（70%）</span><span>{{ number_format($totalCap, 2) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>功德池（4%）</span><span>{{ number_format($globalReserve, 2) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>刚性支出（直推+层碰）</span><span>{{ number_format($fixedSales, 2) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>剩余池（可变奖金上限）</span><span>{{ number_format($remainingPool, 2) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>可变理论 A（对碰+管理）</span><span>{{ number_format($variablePotential, 2) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>可变实发（对碰+管理）</span><span>{{ number_format($variablePaid, 2) }}</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>实发/理论</span><span>{{ number_format($variablePaidRatio * 100, 2) }}%</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>实发/剩余池</span><span>{{ number_format($poolUsageRatio * 100, 2) }}%</span></div>
                                    <div class="d-flex justify-content-between text-dark"><span>K(均值)</span><span>{{ number_format($kAvg, 4) }}</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="text-muted small mb-3">说明：订单口径=订单营业额（PV=金额1:1），系统PV为安置链累计PV，用于对碰/管理计算。</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>奖金项</th>
                                        <th>总拨出金额</th>
                                        <th>占订单营业额</th>
                                        <th>占奖金总额</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bonusItems as $item)
                                        @php
                                            $amount = (float) $item['amount'];
                                            $ratioRevenue = $orderPV > 0 ? ($amount / $orderPV) : 0;
                                            $ratioBonus = $totalBonus > 0 ? ($amount / $totalBonus) : 0;
                                        @endphp
                                        <tr>
                                            <td class="text-dark">{{ $item['label'] }}</td>
                                            <td class="text-dark">{{ number_format($amount, 2) }}</td>
                                            <td class="text-dark">{{ number_format($ratioRevenue * 100, 2) }}%</td>
                                            <td class="text-dark">{{ number_format($ratioBonus * 100, 2) }}%</td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td class="text-dark">合计（奖金）</td>
                                        <td class="text-dark">{{ number_format($totalBonus, 2) }}</td>
                                        <td class="text-dark">{{ number_format($payoutRatioAllOrder * 100, 2) }}%</td>
                                        <td class="text-dark">100%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">周度结果</h5>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Week</th>
                                <th>New</th>
                                <th>Users(估算)</th>
                                <th>Orders</th>
                                <th>订单PV</th>
                                <th>系统PV</th>
                                <th>Cap(70%)</th>
                                <th>Reserve</th>
                                <th>Fixed</th>
                                <th>Variable</th>
                                <th>Remain</th>
                                <th>K</th>
                                <th>直推</th>
                                <th>层碰</th>
                                <th>对碰</th>
                                <th>管理</th>
                                <th>待处理(笔)</th>
                                <th>待处理(金额)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results['weeks'] as $week)
                                <tr>
                                    <td>{{ $week['week_key'] }}</td>
                                    <td>{{ $week['new_users'] ?? 0 }}</td>
                                    <td>{{ $week['total_users'] ?? ($results['total_users'] ?? 0) }}</td>
                                    <td>{{ $week['orders'] ?? 0 }}</td>
                                    <td>{{ number_format($week['total_pv'] ?? 0, 0) }}</td>
                                    <td>{{ number_format($week['system_pv'] ?? ($week['total_pv'] ?? 0), 0) }}</td>
                                    <td>{{ number_format($week['total_cap'] ?? 0, 2) }}</td>
                                    <td>{{ number_format($week['global_reserve'] ?? 0, 2) }}</td>
                                    <td>{{ number_format($week['fixed_sales'] ?? 0, 2) }}</td>
                                    <td>{{ number_format($week['variable_potential'] ?? 0, 2) }}</td>
                                    <td>{{ number_format($week['remaining'] ?? 0, 2) }}</td>
                                    <td>{{ number_format($week['k_factor'], 4) }}</td>
                                    <td>{{ number_format($week['direct_paid'], 2) }}</td>
                                    <td>{{ number_format($week['level_pair_paid'], 2) }}</td>
                                    <td>{{ number_format($week['pair_paid'], 2) }}</td>
                                    <td>{{ number_format($week['matching_paid'], 2) }}</td>
                                    <td>{{ $week['pending_count'] }}</td>
                                    <td>{{ number_format($week['pending_amount'] ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if(!empty($results['months']))
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">月度汇总</h5>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Weeks</th>
                                    <th>New</th>
                                <th>Users(估算)</th>
                                    <th>Orders</th>
                                    <th>订单PV</th>
                                    <th>系统PV</th>
                                    <th>Cap(70%)</th>
                                    <th>Reserve</th>
                                    <th>Fixed</th>
                                    <th>Variable</th>
                                    <th>Remain</th>
                                    <th>K(avg)</th>
                                    <th>直推</th>
                                    <th>层碰</th>
                                    <th>对碰</th>
                                    <th>管理</th>
                                    <th>待处理(笔)</th>
                                    <th>待处理(金额)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results['months'] as $m)
                                    <tr>
                                        <td>{{ $m['month_key'] }}</td>
                                        <td>{{ $m['weeks_count'] }}</td>
                                        <td>{{ $m['new_users'] ?? 0 }}</td>
                                        <td>{{ $m['total_users'] ?? ($results['total_users'] ?? 0) }}</td>
                                        <td>{{ $m['orders'] ?? 0 }}</td>
                                        <td>{{ number_format($m['total_pv'] ?? 0, 0) }}</td>
                                        <td>{{ number_format($m['system_pv'] ?? ($m['total_pv'] ?? 0), 0) }}</td>
                                        <td>{{ number_format($m['total_cap'] ?? 0, 2) }}</td>
                                        <td>{{ number_format($m['global_reserve'] ?? 0, 2) }}</td>
                                        <td>{{ number_format($m['fixed_sales'] ?? 0, 2) }}</td>
                                        <td>{{ number_format($m['variable_potential'] ?? 0, 2) }}</td>
                                        <td>{{ number_format($m['remaining'] ?? 0, 2) }}</td>
                                        <td>{{ number_format($m['k_factor_avg'] ?? 0, 4) }}</td>
                                        <td>{{ number_format($m['direct_paid'] ?? 0, 2) }}</td>
                                        <td>{{ number_format($m['level_pair_paid'] ?? 0, 2) }}</td>
                                        <td>{{ number_format($m['pair_paid'] ?? 0, 2) }}</td>
                                        <td>{{ number_format($m['matching_paid'] ?? 0, 2) }}</td>
                                        <td>{{ $m['pending_count'] ?? 0 }}</td>
                                        <td>{{ number_format($m['pending_amount'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if(!empty($results['quarters']))
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">季度分红预估</h5>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Quarter</th>
                                    <th>订单PV</th>
                                    <th>消费商池</th>
                                    <th>领导人池</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results['quarters'] as $q)
                                    <tr>
                                        <td>{{ $q['quarter_key'] ?? '' }}</td>
                                        <td>{{ number_format($q['total_pv'] ?? 0, 0) }}</td>
                                        <td>{{ number_format($q['pool_stockist'] ?? 0, 2) }}</td>
                                        <td>{{ number_format($q['pool_leader'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @else
            <div class="card h-100">
                <div class="card-body d-flex align-items-center justify-content-center text-muted">
                    提交参数后将在此显示模拟结果。
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

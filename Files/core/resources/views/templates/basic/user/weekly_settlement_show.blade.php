@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="row">
        <div class="col-lg-12">
            @if(!$summary)
                <div class="alert alert-warning">未找到该周的结算记录（{{ $weekKey }}）</div>
            @else
                <div class="card custom--card mb-4">
                    <div class="card-body">
                        <h6 class="mb-3">周结算摘要（{{ $weekKey }}）</h6>
                        <div class="row g-3">
                            <div class="col-md-4">本周PV：{{ showAmount($settlement->total_pv ?? 0) }}</div>
                            <div class="col-md-4">K值：{{ number_format($summary->k_factor ?? 0, 6) }}</div>
                            <div class="col-md-4">封顶：{{ showAmount($summary->cap_used ?? 0) }} / {{ showAmount($summary->cap_amount ?? 0) }}</div>
                        </div>
                    </div>
                </div>

                <div class="card custom--card">
                    <div class="card-body">
                        <h6 class="mb-3">个人结算明细</h6>
                        <div class="table-responsive">
                            <table class="custom--table table">
                                <tbody>
                                    <tr>
                                        <th>对碰次数</th>
                                        <td>{{ $summary->pair_count ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <th>对碰理论</th>
                                        <td>{{ showAmount($summary->pair_theoretical ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <th>对碰实发</th>
                                        <td>{{ showAmount($summary->pair_paid ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <th>管理理论</th>
                                        <td>{{ showAmount($summary->matching_potential ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <th>管理实发</th>
                                        <td>{{ showAmount($summary->matching_paid ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <th>PV扣减</th>
                                        <td>左 {{ showAmount($summary->left_pv_initial ?? 0) }} → {{ showAmount($summary->left_pv_end ?? 0) }}，右 {{ showAmount($summary->right_pv_initial ?? 0) }} → {{ showAmount($summary->right_pv_end ?? 0) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

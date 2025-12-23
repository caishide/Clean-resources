@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="card custom--card p-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="custom--table table">
                            <thead>
                                <tr>
                                    <th>周次</th>
                                    <th>本周PV</th>
                                    <th>K值</th>
                                    <th>对碰实发</th>
                                    <th>管理实发</th>
                                    <th>封顶</th>
                                    <th>详情</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($summaries as $summary)
                                    @php $settlement = $settlements[$summary->week_key] ?? null; @endphp
                                    <tr>
                                        <td>{{ $summary->week_key }}</td>
                                        <td>{{ showAmount($settlement->total_pv ?? 0) }}</td>
                                        <td>{{ number_format($summary->k_factor ?? 0, 6) }}</td>
                                        <td>{{ showAmount($summary->pair_paid ?? 0) }}</td>
                                        <td>{{ showAmount($summary->matching_paid ?? 0) }}</td>
                                        <td>{{ showAmount($summary->cap_used ?? 0) }} / {{ showAmount($summary->cap_amount ?? 0) }}</td>
                                        <td>
                                            <a class="btn btn-sm btn-outline--primary" href="{{ route('user.weekly.settlements.show', $summary->week_key) }}">查看</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">暂无周结算记录</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($summaries->hasPages())
                <div class="mt-4">
                    {{ paginateLinks($summaries) }}
                </div>
            @endif
        </div>
    </div>
@endsection

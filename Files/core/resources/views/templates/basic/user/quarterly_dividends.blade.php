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
                                    <th>季度</th>
                                    <th>池类型</th>
                                    <th>份数/积分</th>
                                    <th>分红金额</th>
                                    <th>状态</th>
                                    <th>时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>{{ $log->quarter_key }}</td>
                                        <td>{{ $log->pool_type }}</td>
                                        <td>
                                            @if($log->pool_type === 'stockist')
                                                {{ $log->shares }}
                                            @else
                                                {{ $log->score }}
                                            @endif
                                        </td>
                                        <td>{{ showAmount($log->dividend_amount ?? 0) }}</td>
                                        <td>{{ $log->status }}</td>
                                        <td>{{ showDateTime($log->created_at) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">暂无季度分红记录</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($logs->hasPages())
                <div class="mt-4">
                    {{ paginateLinks($logs) }}
                </div>
            @endif
        </div>
    </div>
@endsection

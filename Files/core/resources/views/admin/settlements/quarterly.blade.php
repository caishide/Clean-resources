@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">季度分红</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.settlements.quarterly.preview') }}" class="row g-2">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">季度</label>
                            <input type="text" name="quarter" class="form-control" value="{{ old('quarter', $quarter) }}" placeholder="2025-Q4">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn--primary w-100" type="submit">预演</button>
                        </div>
                    </form>
                    <form method="post" action="{{ route('admin.settlements.quarterly.execute') }}" class="row g-2 mt-3">
                        @csrf
                        <input type="hidden" name="quarter" value="{{ old('quarter', $quarter) }}">
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn--success w-100" type="submit">执行结算</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if(!empty($preview))
            <div class="col-lg-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">预演结果</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">总PV：{{ showAmount($preview['total_pv'] ?? 0) }}</div>
                            <div class="col-md-3">消费商池：{{ showAmount($preview['pool_stockist'] ?? 0) }}</div>
                            <div class="col-md-3">领导人池：{{ showAmount($preview['pool_leader'] ?? 0) }}</div>
                            <div class="col-md-3">参与人数：{{ $preview['stockist_count'] ?? 0 }} / {{ $preview['leader_count'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">历史季度分红</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table--light">
                            <thead>
                                <tr>
                                    <th>季度</th>
                                    <th>总PV</th>
                                    <th>消费商池</th>
                                    <th>领导人池</th>
                                    <th>完成时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($settlements as $settlement)
                                    <tr>
                                        <td>{{ $settlement->quarter_key }}</td>
                                        <td>{{ showAmount($settlement->total_pv ?? 0) }}</td>
                                        <td>{{ showAmount($settlement->pool_stockist ?? 0) }}</td>
                                        <td>{{ showAmount($settlement->pool_leader ?? 0) }}</td>
                                        <td>{{ showDateTime($settlement->finalized_at) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">暂无记录</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @if ($settlements->hasPages())
                <div class="mt-3">
                    {{ paginateLinks($settlements) }}
                </div>
            @endif
        </div>
    </div>
@endsection

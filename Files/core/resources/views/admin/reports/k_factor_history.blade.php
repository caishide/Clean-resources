@extends('admin.layouts.app')

@section('title', $pageTitle)

@section('content')
<div class="row">
    {{-- 统计卡片 --}}
    <div class="col-xl-3 col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex">
                    <div class="flex-grow-1">
                        <p class="text-muted fw-medium mb-2">平均周K值</p>
                        <h4 class="mb-0">{{ number_format($stats['avg_weekly_k'], 6) }}</h4>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="avatar-sm rounded">
                            <span class="avatar-title h5 mb-0">
                                <i class="las la-calculator text-primary"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex">
                    <div class="flex-grow-1">
                        <p class="text-muted fw-medium mb-2">最低周K值</p>
                        <h4 class="mb-0 text-danger">{{ number_format($stats['min_weekly_k'], 6) }}</h4>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="avatar-sm rounded">
                            <span class="avatar-title h5 mb-0">
                                <i class="las la-arrow-down text-danger"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex">
                    <div class="flex-grow-1">
                        <p class="text-muted fw-medium mb-2">最高周K值</p>
                        <h4 class="mb-0 text-success">{{ number_format($stats['max_weekly_k'], 6) }}</h4>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="avatar-sm rounded">
                            <span class="avatar-title h5 mb-0">
                                <i class="las la-arrow-up text-success"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex">
                    <div class="flex-grow-1">
                        <p class="text-muted fw-medium mb-2">结算周数</p>
                        <h4 class="mb-0">{{ number_format($stats['total_weeks']) }}</h4>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="avatar-sm rounded">
                            <span class="avatar-title h5 mb-0">
                                <i class="las la-calendar text-info"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- K值趋势图 --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">K值趋势（最近12周）</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table--light">
                        <thead>
                            <tr>
                                <th>周次</th>
                                <th>总PV</th>
                                <th>K值</th>
                                <th>总奖金</th>
                                <th>功德池预留</th>
                                <th>完成时间</th>
                                <th>状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($weeklySettlements as $settlement)
                                <tr>
                                    <td>
                                        <span class="fw-bold">{{ $settlement->week_key }}</span>
                                    </td>
                                    <td>{{ showAmount($settlement->total_pv ?? 0) }}</td>
                                    <td>
                                        @php
                                            $kClass = '';
                                            if ($settlement->k_factor < 0.5) {
                                                $kClass = 'text-danger fw-bold';
                                            } elseif ($settlement->k_factor > 1.2) {
                                                $kClass = 'text-success fw-bold';
                                            }
                                        @endphp
                                        <span class="{{ $kClass }}">{{ number_format($settlement->k_factor, 6) }}</span>
                                    </td>
                                    <td>{{ showAmount($settlement->total_bonus ?? 0) }}</td>
                                    <td>{{ showAmount($settlement->global_reserve ?? 0) }}</td>
                                    <td>{{ showDateTime($settlement->finalized_at) }}</td>
                                    <td>
                                        @if($settlement->k_factor >= 0.8 && $settlement->k_factor <= 1.2)
                                            <span class="badge bg-success">正常</span>
                                        @elseif($settlement->k_factor >= 0.5 && $settlement->k_factor < 0.8)
                                            <span class="badge bg-warning">偏低</span>
                                        @elseif($settlement->k_factor > 1.2)
                                            <span class="badge bg-info">偏高</span>
                                        @else
                                            <span class="badge bg-danger">异常</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">暂无结算记录</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $weeklySettlements->links() }}
            </div>
        </div>
    </div>
</div>

{{-- 季度K值 --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">季度结算K值</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light">
                        <thead>
                            <tr>
                                <th>季度</th>
                                <th>总PV</th>
                                <th>K值</th>
                                <th>护持池</th>
                                <th>领航池</th>
                                <th>完成时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($quarterlySettlements as $settlement)
                                <tr>
                                    <td>
                                        <span class="fw-bold">{{ $settlement->quarter_key }}</span>
                                    </td>
                                    <td>{{ showAmount($settlement->total_pv ?? 0) }}</td>
                                    <td>
                                        <span class="{{ $settlement->k_factor < 0.5 ? 'text-danger fw-bold' : '' }}">
                                            {{ number_format($settlement->k_factor, 6) }}
                                        </span>
                                    </td>
                                    <td>{{ showAmount($settlement->huchi_pool_amount ?? 0) }}</td>
                                    <td>{{ showAmount($settlement->linghang_pool_amount ?? 0) }}</td>
                                    <td>{{ showDateTime($settlement->finalized_at) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">暂无季度结算记录</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $quarterlySettlements->appends(['quarter_page' => $quarterlySettlements->currentPage()])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('breadcrumb-plugins')
    <a href="{{ route('admin.reports.k-factor.export') }}" class="btn btn-outline-secondary">
        <i class="las la-file-export"></i> 导出CSV
    </a>
    <button class="btn btn-outline-primary" onclick="refreshData()">
        <i class="las la-sync"></i> 刷新
    </button>
@endpush

@push('script')
<script>
function refreshData() {
    location.reload();
}

// K值状态指示器
document.addEventListener('DOMContentLoaded', function() {
    const kValues = document.querySelectorAll('.k-factor-value');
    kValues.forEach(function(el) {
        const k = parseFloat(el.dataset.k);
        if (k < 0.5) {
            el.classList.add('text-danger');
        } else if (k > 1.2) {
            el.classList.add('text-success');
        }
    });
});
</script>
@endpush

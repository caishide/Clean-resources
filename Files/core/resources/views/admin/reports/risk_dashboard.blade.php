@extends('admin.layouts.app')

@section('title', $pageTitle)

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">风控仪表盘</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">最新K值</h6>
                                <h2 class="mb-0">{{ number_format($latestK ?? 0, 6) }}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">K值均值</h6>
                                <h2 class="mb-0">{{ number_format($kVolatility->avg ?? 0, 6) }}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">K值标准差</h6>
                                <h2 class="mb-0">{{ number_format($kVolatility->std_dev ?? 0, 6) }}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h6 class="card-title">结算次数</h6>
                                <h2 class="mb-0">{{ number_format($kVolatility->count ?? 0) }}</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <h6>最近4周K值趋势</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>周次</th>
                                        <th>K值</th>
                                        <th>状态</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($kTrend as $k)
                                        <tr>
                                            <td>周 {{ $loop->iteration }}</td>
                                            <td>{{ number_format($k, 6) }}</td>
                                            <td>
                                                @if($k >= 0.8 && $k <= 1.2)
                                                    <span class="badge bg-success">正常</span>
                                                @elseif($k >= 0.5 && $k < 0.8)
                                                    <span class="badge bg-warning">偏低</span>
                                                @elseif($k > 1.2)
                                                    <span class="badge bg-info">偏高</span>
                                                @else
                                                    <span class="badge bg-danger">异常</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center">暂无数据</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

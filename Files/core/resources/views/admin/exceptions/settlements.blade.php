@extends('admin.layouts.app')

@section('panel')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">周结算重复批次</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>周次</th>
                        <th>重复数</th>
                        <th>批次ID列表</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($weeklyDuplicates as $row)
                    <tr>
                        <td>{{ $row->week_key }}</td>
                        <td>{{ $row->duplicate_count }}</td>
                        <td>{{ $row->ids }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center">暂无重复周结算</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $weeklyDuplicates->appends(request()->except('weekly_page'))->links() }}
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">季度分红重复批次</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>季度</th>
                        <th>重复数</th>
                        <th>批次ID列表</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($quarterlyDuplicates as $row)
                    <tr>
                        <td>{{ $row->quarter_key }}</td>
                        <td>{{ $row->duplicate_count }}</td>
                        <td>{{ $row->ids }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center">暂无重复季度批次</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $quarterlyDuplicates->appends(request()->except('quarterly_page'))->links() }}
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">周结算缺少明细</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>批次ID</th>
                        <th>周次</th>
                        <th>总 PV</th>
                        <th>明细数</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($weeklyMissingSummaries as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->week_key }}</td>
                        <td>{{ $row->total_pv }}</td>
                        <td>{{ $row->summary_count }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center">暂无缺少明细的周结算</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $weeklyMissingSummaries->appends(request()->except('missing_page'))->links() }}
    </div>
</div>
@endsection

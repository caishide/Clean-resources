@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="card custom--card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">总余额：{{ showAmount($asset->points ?? 0) }}</div>
                        <div class="col-md-3">自购：{{ showAmount($byType['PURCHASE']->total_points ?? 0) }}</div>
                        <div class="col-md-3">直推：{{ showAmount($byType['DIRECT']->total_points ?? 0) }}</div>
                        <div class="col-md-3">团队：{{ showAmount($byType['TEAM']->total_points ?? 0) }}</div>
                        <div class="col-md-3">签到：{{ showAmount($byType['DAILY']->total_points ?? 0) }}</div>
                    </div>
                    <div class="mt-3">
                        <form method="post" action="{{ route('user.points.checkin') }}">
                            @csrf
                            <button class="btn btn--base" type="submit" @if($checkedIn) disabled @endif>
                                @if($checkedIn) 今日已签到 @else 今日签到 @endif
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card custom--card p-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="custom--table table">
                            <thead>
                                <tr>
                                    <th>来源</th>
                                    <th>数量</th>
                                    <th>说明</th>
                                    <th>时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>{{ $log->source_type }} / {{ $log->source_id }}</td>
                                        <td>{{ showAmount($log->points ?? 0) }}</td>
                                        <td>{{ $log->description }}</td>
                                        <td>{{ showDateTime($log->created_at) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">暂无积分记录</td>
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

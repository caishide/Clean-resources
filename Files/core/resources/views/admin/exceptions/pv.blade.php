@extends('admin.layouts.app')

@section('panel')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">负数 PV 用户</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>用户ID</th>
                        <th>用户名</th>
                        <th>邮箱</th>
                        <th>左区 PV</th>
                        <th>右区 PV</th>
                        <th>总 PV</th>
                        <th>更新时间</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($negativeUsers as $user)
                    <tr>
                        <td>{{ $user->user_id }}</td>
                        <td>{{ $user->username }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->bv_left }}</td>
                        <td>{{ $user->bv_right }}</td>
                        <td>{{ $user->bv_left + $user->bv_right }}</td>
                        <td>{{ $user->updated_at }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center">暂无负数 PV 记录</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $negativeUsers->appends(request()->except('neg_page'))->links() }}
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">PV 重复入账分组</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>来源类型</th>
                        <th>来源ID</th>
                        <th>用户ID</th>
                        <th>位置</th>
                        <th>方向</th>
                        <th>重复数</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($duplicatePvGroups as $row)
                    <tr>
                        <td>{{ $row->source_type }}</td>
                        <td>{{ $row->source_id }}</td>
                        <td>{{ $row->user_id }}</td>
                        <td>{{ $row->position }}</td>
                        <td>{{ $row->trx_type }}</td>
                        <td>{{ $row->duplicate_count }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center">暂无重复入账记录</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $duplicatePvGroups->appends(request()->except('dup_page'))->links() }}
    </div>
</div>
@endsection

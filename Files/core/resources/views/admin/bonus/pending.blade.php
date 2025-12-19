@extends('admin.layouts.app')

@section('panel')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">待处理奖金审核</h5>
        <form class="d-flex" method="get">
            <select name="status" class="form-control form-control-sm me-2" onchange="this.form.submit()">
                <option value="pending" {{ $status=='pending'?'selected':'' }}>待审核</option>
                <option value="released" {{ $status=='released'?'selected':'' }}>已释放</option>
                <option value="rejected" {{ $status=='rejected'?'selected':'' }}>已拒绝</option>
            </select>
            <select name="release_mode" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="manual" {{ $releaseMode=='manual'?'selected':'' }}>人工</option>
                <option value="auto" {{ $releaseMode=='auto'?'selected':'' }}>自动</option>
            </select>
        </form>
    </div>
    <div class="card-body">
        <form method="post" action="{{ route('admin.bonus-review.approve') }}">
            @csrf
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" onclick="document.querySelectorAll('.bonus-check').forEach(cb=>cb.checked=this.checked)"></th>
                            <th>ID</th>
                            <th>用户</th>
                            <th>类型</th>
                            <th>金额</th>
                            <th>来源</th>
                            <th>周次</th>
                            <th>模式</th>
                            <th>状态</th>
                            <th>时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bonuses as $bonus)
                        <tr>
                            <td><input type="checkbox" name="bonus_ids[]" value="{{ $bonus->id }}" class="bonus-check"></td>
                            <td>{{ $bonus->id }}</td>
                            <td>{{ optional($bonus->recipient)->username ?? '-' }}</td>
                            <td>{{ $bonus->bonus_type }}</td>
                            <td>{{ $bonus->amount }}</td>
                            <td>{{ $bonus->source_type }} / {{ $bonus->source_id }}</td>
                            <td>{{ $bonus->accrued_week_key }}</td>
                            <td>{{ $bonus->release_mode }}</td>
                            <td>{{ $bonus->status }}</td>
                            <td>{{ $bonus->created_at }}</td>
                            <td>
                                @if($bonus->status === 'pending' && $bonus->release_mode === 'manual')
                                <button formaction="{{ route('admin.bonus-review.reject', $bonus->id) }}" formmethod="post" class="btn btn-sm btn-danger" onclick="return confirm('确认拒绝？')">拒绝</button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="11" class="text-center">暂无记录</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary mt-2">通过选中</button>
        </form>
        {{ $bonuses->appends(request()->query())->links() }}
    </div>
</div>
@endsection

@extends('admin.layouts.app')

@section('panel')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">调整批次</h5>
        <form method="get" class="d-flex">
            <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">全部</option>
                <option value="pending" {{ $status=='pending'?'selected':'' }}>待确认</option>
                <option value="finalized" {{ $status=='finalized'?'selected':'' }}>已完成</option>
            </select>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>批次号</th>
                        <th>原因</th>
                        <th>引用</th>
                        <th>状态</th>
                        <th>时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                    <tr>
                        <td>{{ $batch->id }}</td>
                        <td>{{ $batch->batch_key }}</td>
                        <td>{{ $batch->reason_type }}</td>
                        <td>{{ $batch->reference_type }} / {{ $batch->reference_id }}</td>
                        <td>{{ $batch->finalized_at ? '已完成' : '待确认' }}</td>
                        <td>{{ $batch->created_at }}</td>
                        <td>
                            <a href="{{ route('admin.adjustment-batches.show', $batch->id) }}" class="btn btn-sm btn-primary">查看</a>
                            @if(!$batch->finalized_at)
                            <form action="{{ route('admin.adjustment-batches.finalize', $batch->id) }}" method="post" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success" onclick="return confirm('确认Finalize该批次？')">Finalize</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center">暂无记录</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $batches->appends(request()->query())->links() }}
    </div>
</div>
@endsection

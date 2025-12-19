@extends('admin.layouts.app')

@section('panel')
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">批次详情：{{ $batch->batch_key }}</h5>
        <div>
            <span class="badge bg-{{ $batch->finalized_at ? 'success' : 'warning' }}">{{ $batch->finalized_at ? '已完成' : '待确认' }}</span>
            @if(!$batch->finalized_at)
            <form action="{{ route('admin.adjustment-batches.finalize', $batch->id) }}" method="post" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-success" onclick="return confirm('确认Finalize该批次？')">Finalize</button>
            </form>
            @endif
        </div>
    </div>
    <div class="card-body">
        <p>原因：{{ $batch->reason_type }}</p>
        <p>引用：{{ $batch->reference_type }} / {{ $batch->reference_id }}</p>
        <p>快照：{{ $batch->snapshot }}</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">条目</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>类型</th>
                        <th>User</th>
                        <th>金额</th>
                        <th>reversal_of_id</th>
                        <th>时间</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr>
                        <td>{{ $entry->id }}</td>
                        <td>{{ $entry->asset_type }}</td>
                        <td>{{ $entry->user_id }}</td>
                        <td>{{ $entry->amount }}</td>
                        <td>{{ $entry->reversal_of_id }}</td>
                        <td>{{ $entry->created_at }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center">暂无记录</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $entries->links() }}
    </div>
</div>
@endsection

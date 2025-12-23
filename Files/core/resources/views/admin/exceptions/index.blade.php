@extends('admin.layouts.app')

@section('panel')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">异常与工单</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>类型</th>
                        <th>说明</th>
                        <th>异常/待处理数</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>订单退款/逆算</td>
                        <td>发货后退款进入调整批次，需人工确认</td>
                        <td>{{ $pendingAdjustments }}</td>
                        <td><a href="{{ route('admin.adjustment.batches') }}" class="btn btn-sm btn-primary">查看调整批次</a></td>
                    </tr>
                    <tr>
                        <td>PV 异常</td>
                        <td>负数 PV / 重复入账</td>
                        <td>负数用户 {{ $negativePvUsers }} / 重复组 {{ $duplicatePvGroups }}</td>
                        <td><a href="{{ route('admin.exceptions.pv') }}" class="btn btn-sm btn-primary">查看 PV 异常</a></td>
                    </tr>
                    <tr>
                        <td>结算异常</td>
                        <td>重复批次 / 周结算缺少明细</td>
                        <td>周重复 {{ $weeklyDuplicateCount }} / 季重复 {{ $quarterlyDuplicateCount }} / 缺明细 {{ $weeklyMissingSummaryCount }}</td>
                        <td><a href="{{ route('admin.exceptions.settlements') }}" class="btn btn-sm btn-primary">查看结算异常</a></td>
                    </tr>
                    <tr>
                        <td>待处理奖金争议</td>
                        <td>待审核/冻结奖金</td>
                        <td>{{ $pendingBonuses }}</td>
                        <td><a href="{{ route('admin.bonus-review.index') }}" class="btn btn-sm btn-primary">查看待处理奖金</a></td>
                    </tr>
                    <tr>
                        <td>工单</td>
                        <td>用户支持/异常申诉</td>
                        <td>{{ $pendingTickets }}</td>
                        <td><a href="{{ route('admin.ticket.pending') }}" class="btn btn-sm btn-primary">查看工单</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

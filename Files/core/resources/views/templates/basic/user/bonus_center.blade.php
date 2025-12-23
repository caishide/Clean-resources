@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="row">
        <div class="col-lg-12">
            @php
                $tabs = [
                    'direct' => '直推奖',
                    'level_pair' => '层碰奖',
                    'pair' => '对碰奖',
                    'matching' => '管理奖',
                    'dividend' => '季度分红',
                    'pending' => '待处理',
                ];
            @endphp
            <ul class="nav nav-pills mb-3">
                @foreach ($tabs as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link @if($type === $key) active @endif" href="{{ route('user.bonus.center', ['type' => $key]) }}">
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="card custom--card p-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        @if ($type === 'pending')
                            <table class="custom--table table">
                                <thead>
                                    <tr>
                                        <th>类型</th>
                                        <th>金额</th>
                                        <th>来源</th>
                                        <th>状态</th>
                                        <th>时间</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pendingBonuses as $bonus)
                                        <tr>
                                            <td>{{ $bonus->bonus_type }}</td>
                                            <td>{{ showAmount($bonus->amount) }}</td>
                                            <td>{{ $bonus->source_type }} / {{ $bonus->source_id }}</td>
                                            <td>{{ $bonus->status }}</td>
                                            <td>{{ showDateTime($bonus->created_at) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-muted text-center" colspan="100%">暂无待处理记录</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        @else
                            <table class="custom--table table">
                                <thead>
                                    <tr>
                                        <th>类型</th>
                                        <th>金额</th>
                                        <th>来源</th>
                                        <th>时间</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $trx)
                                        <tr>
                                            <td>{{ $trx->remark }}</td>
                                            <td>
                                                <span class="@if ($trx->trx_type === '+') text--success @else text--danger @endif">
                                                    {{ $trx->trx_type }} {{ showAmount($trx->amount) }}
                                                </span>
                                            </td>
                                            <td>{{ $trx->source_type }} / {{ $trx->source_id }}</td>
                                            <td>{{ showDateTime($trx->created_at) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-muted text-center" colspan="100%">暂无记录</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>

            @if ($type === 'pending' && $pendingBonuses->hasPages())
                <div class="mt-4">
                    {{ paginateLinks($pendingBonuses) }}
                </div>
            @elseif ($type !== 'pending' && $transactions->hasPages())
                <div class="mt-4">
                    {{ paginateLinks($transactions) }}
                </div>
            @endif
        </div>
    </div>
@endsection

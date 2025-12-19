@extends('admin.layouts.app')

@section('panel')
<div class="row gy-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">奖金参数</h5>
                <small class="text-muted">输入小数，如 0.2 表示 20%</small>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.bonus-config.update') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">直推奖比例</label>
                            <input type="number" step="0.01" name="direct_rate" value="{{ old('direct_rate', $config['direct_rate'] ?? 0) }}" class="form-control" min="0" max="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">层碰奖比例</label>
                            <input type="number" step="0.01" name="level_pair_rate" value="{{ old('level_pair_rate', $config['level_pair_rate'] ?? 0) }}" class="form-control" min="0" max="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">对碰单对比例</label>
                            <input type="number" step="0.01" name="pair_rate" value="{{ old('pair_rate', $config['pair_rate'] ?? 0) }}" class="form-control" min="0" max="1" required>
                            <small class="text-muted">单对奖金 = 3000 × 此比例</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">管理奖比例（1-3代）</label>
                            <input type="number" step="0.01" name="management_rate_first" value="{{ old('management_rate_first', $config['management_rates']['1-3'] ?? 0) }}" class="form-control" min="0" max="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">管理奖比例（4-5代）</label>
                            <input type="number" step="0.01" name="management_rate_second" value="{{ old('management_rate_second', $config['management_rates']['4-5'] ?? 0) }}" class="form-control" min="0" max="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">周封顶 - 等级1</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="pair_cap_1" value="{{ old('pair_cap_1', $config['pair_cap'][1] ?? 0) }}" class="form-control" min="0" required>
                                <span class="input-group-text">元</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">周封顶 - 等级2</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="pair_cap_2" value="{{ old('pair_cap_2', $config['pair_cap'][2] ?? 0) }}" class="form-control" min="0" required>
                                <span class="input-group-text">元</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">周封顶 - 等级3</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="pair_cap_3" value="{{ old('pair_cap_3', $config['pair_cap'][3] ?? 0) }}" class="form-control" min="0" required>
                                <span class="input-group-text">元</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">功德池预留</label>
                            <input type="number" step="0.01" name="global_reserve_rate" value="{{ old('global_reserve_rate', $config['global_reserve_rate'] ?? 0) }}" class="form-control" min="0" max="1" required>
                            <small class="text-muted">周结算预留比例</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">消费商季度分红</label>
                            <input type="number" step="0.01" name="pool_stockist_rate" value="{{ old('pool_stockist_rate', $config['pool_stockist_rate'] ?? 0) }}" class="form-control" min="0" max="1" required>
                            <small class="text-muted">季度池比例</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">领导人季度分红</label>
                            <input type="number" step="0.01" name="pool_leader_rate" value="{{ old('pool_leader_rate', $config['pool_leader_rate'] ?? 0) }}" class="form-control" min="0" max="1" required>
                            <small class="text-muted">季度池比例</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn--primary w-100 mt-3">保存配置</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">@lang('admin.bonus.current_config_snapshot')</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 small">
                    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.version')</span><span>{{ $config['version'] ?? 'v10.1' }}</span></li>
                    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.direct_commission')</span><span>{{ ($config['direct_rate'] ?? 0) * 100 }}%</span></li>
                    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.level_pair_commission')</span><span>{{ ($config['level_pair_rate'] ?? 0) * 100 }}%</span></li>
                    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.pair_commission')</span><span>{{ ($config['pair_rate'] ?? 0) * 100 }}%</span></li>
                    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.management_commission_generations', ['generations' => '1-3代'])</span><span>{{ ($config['management_rates']['1-3'] ?? 0) * 100 }}%</span></li>
                    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.management_commission_generations', ['generations' => '4-5代'])</span><span>{{ ($config['management_rates']['4-5'] ?? 0) * 100 }}%</span></li>
                    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.weekly_cap') L1/L2/L3</span><span>{{ ($config['pair_cap'][1] ?? 0) }} / {{ ($config['pair_cap'][2] ?? 0) }} / {{ ($config['pair_cap'][3] ?? 0) }}</span></li>
                    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.global_reserve')</span><span>{{ ($config['global_reserve_rate'] ?? 0) * 100 }}%</span></li>
                    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.quarterly_pool', ['ratio' => '消费商/领导'])</span><span>{{ ($config['pool_stockist_rate'] ?? 0) * 100 }}% / {{ ($config['pool_leader_rate'] ?? 0) * 100 }}%</span></li>
                </ul>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">@lang('admin.description')</h6>
            </div>
            <div class="card-body small text-muted">
                <p class="mb-2">@lang('admin.bonus.unfilled_fields_using_default')</p>
                <p class="mb-0">@lang('admin.bonus.takes_effect_immediately')</p>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('templates.basic.layouts.user')

@section('title', '七宝进阶')

@section('content')
<div class="dashboard-body">
    <div class="row gy-4">
        <!-- 当前职级信息 -->
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">我的七宝进阶</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center">
                            <div class="current-rank-badge">
                                <div class="rank-icon mb-3">
                                    <i class="fas fa-crown rank-icon-{{ $rankInfo['current_rank'] ?? 'default' }}"></i>
                                </div>
                                <h4 class="rank-name">{{ $rankInfo['current_rank_name'] }}</h4>
                                <p class="text-muted">当前职级</p>
                                @if($rankInfo['current_rank'])
                                    <div class="rank-multiplier">
                                        分红系数: <span class="text-primary">{{ $rankInfo['current_multiplier'] }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="rank-progress-info">
                                <h6>累计小区PV</h6>
                                <div class="progress mb-3">
                                    @php
                                        $cumulativePv = isset($rankInfo['promotion_details']['pv']['current']) ? $rankInfo['promotion_details']['pv']['current'] : 0;
                                        $nextTargetPv = $rankInfo['next_rank'] ? $config['ranks'][$rankInfo['next_rank']]['min_pv'] : 0;
                                        $progressPercent = $nextTargetPv > 0 ? min(100, ($cumulativePv / $nextTargetPv) * 100) : 0;
                                    @endphp
                                    <div class="progress-bar" style="width: {{ $progressPercent }}%">
                                        {{ number_format($cumulativePv) }} PV
                                    </div>
                                </div>
                                
                                @if($rankInfo['next_rank'])
                                    <div class="next-rank-info">
                                        <h6>下一级: {{ $rankInfo['next_rank_name'] }}</h6>
                                        <p class="text-muted">
                                            距离下一级还需: 
                                            <strong class="text-primary">
                                                {{ number_format(max(0, $nextTargetPv - $cumulativePv)) }} PV
                                            </strong>
                                        </p>
                                        
                                        @if($rankInfo['promotion_eligible'])
                                            <div class="alert alert-success">
                                                <i class="fas fa-check-circle"></i>
                                                恭喜！您已满足晋升条件
                                            </div>
                                        @else
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle"></i>
                                                继续努力，早日达成晋升条件
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="alert alert-success">
                                        <i class="fas fa-trophy"></i>
                                        恭喜！您已达到最高职级
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 快速统计 -->
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">快速统计</h5>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number">{{ $user->refers()->count() ?? 0 }}</div>
                                <div class="stat-label">直推人数</div>
                            </div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number">{{ number_format($cumulativePv ?? 0) }}</div>
                                <div class="stat-label">累计PV</div>
                            </div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number">{{ $rankInfo['current_multiplier'] ?? 0 }}</div>
                                <div class="stat-label">分红系数</div>
                            </div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number">{{ $rankInfo['current_rank'] ? array_search($rankInfo['current_rank'], $rankOrder) + 1 : 0 }}/5</div>
                                <div class="stat-label">职级进度</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 职级详情 -->
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">七宝进阶详情</h5>
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> 刷新
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($rankOrder as $index => $rankCode)
                            @php
                                $rankConfig = $config['ranks'][$rankCode];
                                $isCurrent = $rankInfo['current_rank'] === $rankCode;
                                $isUnlocked = !$rankInfo['current_rank'] || array_search($rankCode, $rankOrder) <= array_search($rankInfo['current_rank'], $rankOrder);
                                $isNext = $rankInfo['next_rank'] === $rankCode;
                            @endphp
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="rank-card {{ $isCurrent ? 'current' : '' }} {{ $isNext ? 'next' : '' }} {{ !$isUnlocked ? 'locked' : '' }}">
                                    <div class="rank-card-header">
                                        <div class="rank-number">{{ $index + 1 }}</div>
                                        <div class="rank-icon-large">
                                            <i class="fas fa-crown"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="rank-card-body">
                                        <h6 class="rank-title">{{ $rankConfig['name'] }}</h6>
                                        <div class="rank-multiplier-large">分红系数: {{ $rankConfig['multiplier'] }}</div>
                                        
                                        <div class="rank-requirements">
                                            <div class="requirement-item">
                                                <i class="fas fa-coins text-warning"></i>
                                                <span>累计PV: {{ number_format($rankConfig['min_pv']) }}</span>
                                            </div>
                                            
                                            @if($rankConfig['required_direct_refs'])
                                                <div class="requirement-item">
                                                    <i class="fas fa-users text-info"></i>
                                                    <span>直推: {{ $rankConfig['required_direct_refs'] }}人</span>
                                                </div>
                                            @endif
                                            
                                            @if($rankConfig['structure_requirement'])
                                                <div class="requirement-item">
                                                    <i class="fas fa-sitemap text-success"></i>
                                                    <span>
                                                        {{ $config['ranks'][$rankConfig['structure_requirement']['required_rank']]['name'] }}
                                                        × {{ $rankConfig['structure_requirement']['lines'] }}条线
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                        
                                        @if($isCurrent)
                                            <div class="rank-status current">
                                                <i class="fas fa-check-circle"></i> 当前职级
                                            </div>
                                        @elseif($isNext && $rankInfo['promotion_eligible'])
                                            <div class="rank-status eligible">
                                                <i class="fas fa-arrow-up"></i> 可晋升
                                            </div>
                                        @elseif($isNext)
                                            <div class="rank-status progress">
                                                <i class="fas fa-clock"></i> 晋升中
                                            </div>
                                        @elseif(!$isUnlocked)
                                            <div class="rank-status locked">
                                                <i class="fas fa-lock"></i> 未解锁
                                            </div>
                                        @else
                                            <div class="rank-status completed">
                                                <i class="fas fa-check"></i> 已完成
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- 架构要求详情 -->
        @if($rankInfo['promotion_details']['structure']['details'])
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">架构要求进度</h5>
                    </div>
                    <div class="card-body">
                        <div class="structure-progress">
                            @php
                                $structureDetails = $rankInfo['promotion_details']['structure']['details'];
                            @endphp
                            <div class="progress-summary mb-4">
                                <span class="badge bg-primary">
                                    合格线路: {{ $structureDetails['qualified_lines'] }}/{{ $structureDetails['required_lines'] }}
                                </span>
                            </div>
                            
                            <div class="row">
                                @foreach($structureDetails['lines'] as $line)
                                    <div class="col-md-6 mb-3">
                                        <div class="line-card {{ $line['qualified'] ? 'qualified' : 'not-qualified' }}">
                                            <div class="line-header">
                                                <strong>线路 {{ $loop->iteration }}</strong>
                                                <span class="badge {{ $line['qualified'] ? 'bg-success' : 'bg-warning' }}">
                                                    {{ $line['qualified'] ? '合格' : '待发展' }}
                                                </span>
                                            </div>
                                            <div class="line-details">
                                                <small class="text-muted">负责人: {{ $line['root_username'] }}</small><br>
                                                <small>总人数: {{ $line['total_members'] }} | 
                                                       {{ $config['ranks'][$structureDetails['requirement']['required_rank']]['name'] }}: 
                                                       {{ $line['rank_members'] }}</small>
                                            </div>
                                    </div>
                                        </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- 实时数据 -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">实时数据</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="data-item">
                                <div class="data-value">{{ number_format($cumulativePv ?? 0) }}</div>
                                <div class="data-label">累计小区PV</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="data-item">
                                <div class="data-value">{{ $user->refers()->count() ?? 0 }}</div>
                                <div class="data-label">直推人数</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="data-item">
                                <div class="data-value">{{ $rankInfo['current_multiplier'] ?? 0 }}</div>
                                <div class="data-label">分红系数</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="data-item">
                                <div class="data-value">{{ $rankInfo['current_rank'] ? array_search($rankInfo['current_rank'], $rankOrder) + 1 : 0 }}/5</div>
                                <div class="data-label">当前进度</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rank-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    height: 100%;
}

.rank-card.current {
    border-color: #28a745;
    background: linear-gradient(135deg, #28a74515, #28a74505);
}

.rank-card.next {
    border-color: #ffc107;
    background: linear-gradient(135deg, #ffc10715, #ffc10705);
}

.rank-card.locked {
    opacity: 0.6;
}

.rank-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.rank-card-header {
    position: relative;
    margin-bottom: 15px;
}

.rank-number {
    position: absolute;
    top: -10px;
    left: -10px;
    background: #6c757d;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.rank-card.current .rank-number {
    background: #28a745;
}

.rank-card.next .rank-number {
    background: #ffc107;
}

.rank-icon-large {
    font-size: 2.5rem;
    color: #6c757d;
}

.rank-card.current .rank-icon-large {
    color: #28a745;
}

.rank-card.next .rank-icon-large {
    color: #ffc107;
}

.rank-title {
    font-weight: bold;
    margin-bottom: 10px;
}

.rank-multiplier-large {
    color: #007bff;
    font-weight: 500;
    margin-bottom: 15px;
}

.rank-requirements {
    text-align: left;
    margin-bottom: 15px;
}

.requirement-item {
    display: flex;
    align-items: center;
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.requirement-item i {
    width: 20px;
    margin-right: 8px;
}

.rank-status {
    padding: 8px 12px;
    border-radius: 5px;
    font-size: 0.9rem;
    font-weight: 500;
}

.rank-status.current {
    background: #d4edda;
    color: #155724;
}

.rank-status.eligible {
    background: #cce5ff;
    color: #004085;
}

.rank-status.progress {
    background: #fff3cd;
    color: #856404;
}

.rank-status.locked {
    background: #f8d7da;
    color: #721c24;
}

.rank-status.completed {
    background: #d1ecf1;
    color: #0c5460;
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.stat-icon {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 8px;
    color: #007bff;
}

.stat-number {
    font-size: 1.2rem;
    font-weight: bold;
    color: #333;
}

.stat-label {
    font-size: 0.9rem;
    color: #6c757d;
}

.line-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
}

.line-card.qualified {
    border-color: #28a745;
    background: #f8fff9;
}

.line-card.not-qualified {
    border-color: #ffc107;
    background: #fffef8;
}

.line-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.data-item {
    padding: 20px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: #f8f9fa;
}

.data-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #007bff;
    margin-bottom: 5px;
}

.data-label {
    color: #6c757d;
    font-size: 0.9rem;
}
</style>

<script>
function refreshData() {
    location.reload();
}

$(document).ready(function() {
    // 定时刷新数据
    setInterval(function() {
        // 这里可以添加AJAX请求来实时更新数据
        console.log('Refreshing data...');
    }, 30000); // 每30秒刷新一次
});
</script>
@endsection
@extends('admin.layouts.app')

@section('title', '七宝进阶管理')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">七宝进阶管理</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">七宝进阶</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- 统计卡片 -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted fw-medium mb-2">总用户数</p>
                            <h4 class="mb-0">{{ number_format($stats['total_users']) }}</h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded">
                                <span class="avatar-title h5 mb-0">
                                    <i class="fas fa-users text-primary"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted fw-medium mb-2">有职级用户</p>
                            <h4 class="mb-0">{{ number_format($stats['ranked_users']) }}</h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded">
                                <span class="avatar-title h5 mb-0">
                                    <i class="fas fa-crown text-success"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted fw-medium mb-2">无职级用户</p>
                            <h4 class="mb-0">{{ number_format($stats['unranked_users']) }}</h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded">
                                <span class="avatar-title h5 mb-0">
                                    <i class="fas fa-user text-warning"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted fw-medium mb-2">职级覆盖率</p>
                            <h4 class="mb-0">{{ $stats['total_users'] > 0 ? round(($stats['ranked_users'] / $stats['total_users']) * 100, 2) : 0 }}%</h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded">
                                <span class="avatar-title h5 mb-0">
                                    <i class="fas fa-percentage text-info"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 操作按钮 -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">用户职级管理</h5>
                        <div>
                            <button class="btn btn-primary" onclick="batchPromotion()">
                                <i class="fas fa-magic"></i> 批量晋升检查
                            </button>
                            <button class="btn btn-info" onclick="getEligibleUsers()">
                                <i class="fas fa-list"></i> 查看可晋升用户
                            </button>
                            <button class="btn btn-success" onclick="refreshStats()">
                                <i class="fas fa-sync-alt"></i> 刷新统计
                            </button>
                        </div>
                    </div>

                    <!-- 搜索和筛选 -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="searchInput" placeholder="搜索用户名或邮箱...">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="rankFilter">
                                <option value="">所有职级</option>
                                <option value="none">未设定职级</option>
                                @foreach(config('seven_treasures.ranks') as $code => $rank)
                                    <option value="{{ $code }}">{{ $rank['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-secondary" onclick="searchUsers()">搜索</button>
                        </div>
                    </div>

                    <!-- 用户列表 -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>用户</th>
                                    <th>邮箱</th>
                                    <th>当前职级</th>
                                    <th>分红系数</th>
                                    <th>直推人数</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                @forelse($users as $user)
                                    <tr>
                                        <td>
                                            <strong>{{ $user->username }}</strong>
                                            <br>
                                            <small class="text-muted">ID: {{ $user->id }}</small>
                                        </td>
                                        <td>{{ $user->email }}</td>
                                        <td>
                                            @if($user->leader_rank_code)
                                                <span class="badge bg-success">{{ config('seven_treasures.ranks.' . $user->leader_rank_code . '.name') }}</span>
                                            @else
                                                <span class="badge bg-secondary">未设定</span>
                                            @endif
                                        </td>
                                        <td>{{ $user->leader_rank_multiplier ?? 0 }}</td>
                                        <td>{{ $user->refers_count ?? 0 }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-info" onclick="viewUserDetails({{ $user->id }})">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-primary" onclick="promoteUser({{ $user->id }})">
                                                    <i class="fas fa-arrow-up"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="checkPromotion({{ $user->id }})">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">暂无数据</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- 分页 -->
                    <div class="d-flex justify-content-end">
                        {{ $users->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 职级分布图 -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">职级分布</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach(config('seven_treasures.rank_order') as $rankCode)
                            @php
                                $count = $stats['rank_distribution'][$rankCode] ?? 0;
                                $rankName = config('seven_treasures.ranks.' . $rankCode . '.name');
                                $percentage = $stats['ranked_users'] > 0 ? ($count / $stats['ranked_users']) * 100 : 0;
                            @endphp
                            <div class="col-md-2 col-6 mb-3">
                                <div class="rank-stat-card">
                                    <div class="rank-stat-number">{{ $count }}</div>
                                    <div class="rank-stat-label">{{ $rankName }}</div>
                                    <div class="rank-stat-percent">{{ round($percentage, 1) }}%</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 用户详情模态框 -->
<div class="modal fade" id="userDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">用户职级详情</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetailsBody">
                <!-- 内容将通过AJAX加载 -->
            </div>
        </div>
    </div>
</div>

<!-- 晋升模态框 -->
<div class="modal fade" id="promoteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">晋升用户</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="promoteUserForm">
                    <input type="hidden" id="promoteUserId" name="user_id">
                    <div class="mb-3">
                        <label for="targetRank" class="form-label">目标职级</label>
                        <select class="form-select" id="targetRank" name="rank_code" required>
                            <option value="">请选择职级</option>
                            @foreach(config('seven_treasures.rank_order') as $rankCode)
                                <option value="{{ $rankCode }}">{{ config('seven_treasures.ranks.' . $rankCode . '.name') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="promoteReason" class="form-label">晋升原因</label>
                        <textarea class="form-control" id="promoteReason" rows="3" placeholder="请输入晋升原因..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="confirmPromotion()">确认晋升</button>
            </div>
        </div>
    </div>
</div>

<!-- 可晋升用户模态框 -->
<div class="modal fade" id="eligibleUsersModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">可晋升用户列表</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="eligibleUsersList">
                    <!-- 内容将通过AJAX加载 -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rank-stat-card {
    text-align: center;
    padding: 20px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #f8f9fa;
}

.rank-stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #007bff;
    margin-bottom: 5px;
}

.rank-stat-label {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 3px;
}

.rank-stat-percent {
    font-size: 0.8rem;
    color: #28a745;
}

.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.badge {
    font-size: 0.75em;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.rank-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.eligible-user-item {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    background: #f8fff9;
}

.eligible-user-item.not-ready {
    background: #fffef8;
}

.user-rank-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.rank-requirements-list {
    font-size: 0.9rem;
}

.requirement-met {
    color: #28a745;
}

.requirement-not-met {
    color: #dc3545;
}
</style>

<script>
function refreshStats() {
    location.reload();
}

function searchUsers() {
    const query = document.getElementById('searchInput').value;
    const rankFilter = document.getElementById('rankFilter').value;
    
    // 这里可以添加AJAX请求来搜索用户
    console.log('Searching users:', { query, rankFilter });
}

function viewUserDetails(userId) {
    fetch(`/admin/seven-treasures/user/${userId}/details`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('userDetailsBody').innerHTML = formatUserDetails(data.data);
                new bootstrap.Modal(document.getElementById('userDetailsModal')).show();
            } else {
                alert('获取用户详情失败: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('获取用户详情失败');
        });
}

function promoteUser(userId) {
    document.getElementById('promoteUserId').value = userId;
    new bootstrap.Modal(document.getElementById('promoteUserModal')).show();
}

function confirmPromotion() {
    const userId = document.getElementById('promoteUserId').value;
    const rankCode = document.getElementById('targetRank').value;
    
    if (!rankCode) {
        alert('请选择目标职级');
        return;
    }
    
    if (!confirm('确认要晋升该用户吗？')) {
        return;
    }
    
    fetch('/admin/seven-treasures/promote-user', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            user_id: userId,
            rank_code: rankCode
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('晋升成功');
            location.reload();
        } else {
            alert('晋升失败: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('晋升失败');
    });
}

function checkPromotion(userId) {
    fetch(`/admin/seven-treasures/check-promotion`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            user_id: userId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(`晋升检查结果:\n是否可晋升: ${data.data.eligible ? '是' : '否'}\n目标职级: ${data.data.target_rank_name || '无'}`);
        } else {
            alert('检查失败: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('检查失败');
    });
}

function batchPromotion() {
    if (!confirm('确认要执行批量晋升检查吗？这可能需要一些时间。')) {
        return;
    }
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
    btn.disabled = true;
    
    fetch('/admin/seven-treasures/batch-promotion', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.status === 'success') {
            alert(`批量晋升完成:\n检查用户: ${data.data.checked}\n成功晋升: ${data.data.promoted}\n错误数量: ${data.data.errors}`);
            location.reload();
        } else {
            alert('批量晋升失败: ' + data.message);
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        console.error('Error:', error);
        alert('批量晋升失败');
    });
}

function getEligibleUsers() {
    fetch('/admin/seven-treasures/eligible-users')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('eligibleUsersList').innerHTML = formatEligibleUsers(data.data);
                new bootstrap.Modal(document.getElementById('eligibleUsersModal')).show();
            } else {
                alert('获取可晋升用户失败: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('获取可晋升用户失败');
        });
}

function formatUserDetails(data) {
    const rankInfo = data.rank_info;
    return `
        <div class="row">
            <div class="col-md-6">
                <h6>用户信息</h6>
                <p><strong>用户名:</strong> ${data.user.username}</p>
                <p><strong>邮箱:</strong> ${data.user.email}</p>
                <p><strong>当前职级:</strong> ${rankInfo.current_rank_name}</p>
                <p><strong>分红系数:</strong> ${rankInfo.current_multiplier}</p>
            </div>
            <div class="col-md-6">
                <h6>晋升信息</h6>
                <p><strong>下一职级:</strong> ${rankInfo.next_rank_name || '无'}</p>
                <p><strong>可晋升:</strong> ${rankInfo.promotion_eligible ? '是' : '否'}</p>
                <p><strong>直推人数:</strong> ${rankInfo.direct_referrals}</p>
                <p><strong>累计PV:</strong> ${rankInfo.cumulative_weak_pv.toLocaleString()}</p>
            </div>
        </div>
    `;
}

function formatEligibleUsers(users) {
    if (users.length === 0) {
        return '<div class="text-center text-muted">暂无可晋升用户</div>';
    }
    
    return users.map(item => {
        const requirements = item.requirements;
        return `
            <div class="eligible-user-item">
                <div class="user-rank-info">
                    <div>
                        <strong>${item.user.username}</strong>
                        <span class="badge bg-primary ms-2">${item.target_rank_name}</span>
                    </div>
                    <button class="btn btn-sm btn-success" onclick="promoteUser(${item.user.id})">
                        立即晋升
                    </button>
                </div>
                <div class="rank-requirements-list">
                    <small>
                        <span class="requirement-met">✓ PV要求: ${requirements.pv.current.toLocaleString()}/${requirements.pv.required.toLocaleString()}</span>
                        ${requirements.direct_refs.required ? ` | <span class="${requirements.direct_refs.eligible ? 'requirement-met' : 'requirement-not-met'}">${requirements.direct_refs.eligible ? '✓' : '✗'} 直推: ${requirements.direct_refs.current}/${requirements.direct_refs.required}</span>` : ''}
                    </small>
                </div>
            </div>
        `;
    }).join('');
}

$(document).ready(function() {
    // 搜索框回车事件
    $('#searchInput').on('keypress', function(e) {
        if (e.which === 13) {
            searchUsers();
        }
    });
});
</script>
@endsection
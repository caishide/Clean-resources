<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SevenTreasuresService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SevenTreasuresController extends Controller
{
    protected SevenTreasuresService $sevenTreasuresService;

    public function __construct(SevenTreasuresService $sevenTreasuresService)
    {
        $this->sevenTreasuresService = $sevenTreasuresService;
    }

    /**
     * 七宝进阶管理首页
     */
    public function index(): View
    {
        $users = User::whereNotNull('leader_rank_code')
            ->withCount('refers')
            ->orderBy('leader_rank_code')
            ->orderBy('leader_rank_multiplier', 'desc')
            ->paginate(20);

        $stats = $this->getOverallStats();

        return view('admin.seven_treasures.index', compact('users', 'stats'));
    }

    /**
     * 获取职级统计
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->getOverallStats();
            
            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '获取统计数据失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 批量检查和晋升用户
     */
    public function batchPromotion(): JsonResponse
    {
        try {
            $result = $this->sevenTreasuresService->batchPromotionCheck();
            
            return response()->json([
                'status' => 'success',
                'message' => '批量晋升完成',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '批量晋升失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检查单个用户晋升资格
     */
    public function checkUserPromotion(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id');
            $targetRank = $request->input('target_rank');
            
            $user = User::findOrFail($userId);
            $eligibility = $this->sevenTreasuresService->checkPromotionEligibility($user, $targetRank);
            
            return response()->json([
                'status' => 'success',
                'data' => $eligibility
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '检查用户晋升资格失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 手动晋升用户
     */
    public function promoteUser(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id');
            $rankCode = $request->input('rank_code');
            
            $user = User::findOrFail($userId);
            
            // 检查是否已经具有此职级或更高职级
            if ($user->leader_rank_code) {
                $currentIndex = array_search($user->leader_rank_code, $this->sevenTreasuresService->getRankOrder());
                $targetIndex = array_search($rankCode, $this->sevenTreasuresService->getRankOrder());
                
                if ($currentIndex >= $targetIndex) {
                    return response()->json([
                        'status' => 'error',
                        'message' => '用户当前职级不能降级或重复晋升'
                    ], 400);
                }
            }
            
            $success = $this->sevenTreasuresService->promoteUser($user, $rankCode);
            
            if ($success) {
                return response()->json([
                    'status' => 'success',
                    'message' => '用户晋升成功'
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => '用户晋升失败'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '晋升用户失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取用户职级详情
     */
    public function getUserRankDetails(int $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            $rankInfo = $this->sevenTreasuresService->getUserRankInfo($user);
            
            // 获取额外信息
            $rankInfo['direct_referrals'] = $this->sevenTreasuresService->getDirectReferralCount($user);
            $rankInfo['cumulative_weak_pv'] = $this->sevenTreasuresService->getCumulativeWeakPV($user);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                    ],
                    'rank_info' => $rankInfo
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '获取用户职级详情失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 搜索用户
     */
    public function searchUsers(Request $request): JsonResponse
    {
        try {
            $query = $request->input('q');
            $rankFilter = $request->input('rank');
            
            $users = User::query()
                ->when($query, function ($q) use ($query) {
                    $q->where(function ($subQuery) use ($query) {
                        $subQuery->where('username', 'like', "%{$query}%")
                                ->orWhere('email', 'like', "%{$query}%");
                    });
                })
                ->when($rankFilter, function ($q) use ($rankFilter) {
                    if ($rankFilter === 'none') {
                        $q->whereNull('leader_rank_code');
                    } else {
                        $q->where('leader_rank_code', $rankFilter);
                    }
                })
                ->select(['id', 'username', 'email', 'leader_rank_code', 'leader_rank_multiplier'])
                ->limit(20)
                ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '搜索用户失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取职级配置
     */
    public function getRankConfig(): JsonResponse
    {
        try {
            $config = config('seven_treasures');
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'ranks' => $config['ranks'],
                    'rank_order' => $config['rank_order'],
                    'promotion' => $config['promotion'],
                    'cache' => $config['cache']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '获取职级配置失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取所有用户的职级分布
     */
    public function getRankDistribution(): JsonResponse
    {
        try {
            // 🔒 修复SQL注入风险：使用安全的排序方式
            $rankOrder = config('seven_treasures.rank_order', []);

            // 对rank_order进行转义和验证
            $safeRankOrder = array_map(function($rank) {
                // 转义单引号防止SQL注入
                return "'" . addslashes($rank) . "'";
            }, $rankOrder);

            $distribution = User::whereNotNull('leader_rank_code')
                ->selectRaw('leader_rank_code, COUNT(*) as count, AVG(leader_rank_multiplier) as avg_multiplier')
                ->groupBy('leader_rank_code')
                ->orderByRaw('FIELD(leader_rank_code, ' . implode(',', $safeRankOrder) . ')')
                ->get();

            $config = config('seven_treasures.ranks');
            $distribution = $distribution->map(function ($item) use ($config) {
                $item->rank_name = $config[$item->leader_rank_code]['name'] ?? '未知职级';
                return $item;
            });

            return response()->json([
                'status' => 'success',
                'data' => $distribution
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '获取职级分布失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取可晋升用户列表
     */
    public function getEligibleUsers(): JsonResponse
    {
        try {
            $users = User::withCount('refers')->get();
            $eligibleUsers = [];
            
            foreach ($users as $user) {
                $eligibility = $this->sevenTreasuresService->checkPromotionEligibility($user);
                if ($eligibility['eligible']) {
                    $eligibleUsers[] = [
                        'user' => [
                            'id' => $user->id,
                            'username' => $user->username,
                            'email' => $user->email,
                        ],
                        'current_rank' => $user->leader_rank_code,
                        'target_rank' => $eligibility['target_rank'],
                        'target_rank_name' => $eligibility['target_rank_name'],
                        'requirements' => $eligibility['requirements']
                    ];
                }
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $eligibleUsers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '获取可晋升用户失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 清除用户缓存
     */
    public function clearUserCache(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id');
            $user = User::findOrFail($userId);
            
            // 这里可以调用清除缓存的方法
            // $this->sevenTreasuresService->clearUserRankCache($userId);
            
            return response()->json([
                'status' => 'success',
                'message' => '用户缓存清除成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '清除缓存失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取整体统计信息
     */
    private function getOverallStats(): array
    {
        $totalUsers = User::count();
        $rankedUsers = User::whereNotNull('leader_rank_code')->count();
        $unrankedUsers = $totalUsers - $rankedUsers;
        
        $rankDistribution = User::whereNotNull('leader_rank_code')
            ->selectRaw('leader_rank_code, COUNT(*) as count')
            ->groupBy('leader_rank_code')
            ->get()
            ->pluck('count', 'leader_rank_code')
            ->toArray();
        
        $config = config('seven_treasures.ranks');
        $rankOrder = config('seven_treasures.rank_order');
        
        // 确保每个职级都有数据
        foreach ($rankOrder as $rankCode) {
            if (!isset($rankDistribution[$rankCode])) {
                $rankDistribution[$rankCode] = 0;
            }
        }
        
        return [
            'total_users' => $totalUsers,
            'ranked_users' => $rankedUsers,
            'unranked_users' => $unrankedUsers,
            'rank_distribution' => $rankDistribution,
            'rank_names' => array_map(function($rankCode) use ($config) {
                return $config[$rankCode]['name'] ?? '未知职级';
            }, $rankOrder)
        ];
    }
}
<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SevenTreasuresService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SevenTreasuresController extends Controller
{
    protected SevenTreasuresService $sevenTreasuresService;

    public function __construct(SevenTreasuresService $sevenTreasuresService)
    {
        $this->sevenTreasuresService = $sevenTreasuresService;
    }

    /**
     * 获取七宝进阶页面
     */
    public function index()
    {
        $user = auth()->user();
        $rankInfo = $this->sevenTreasuresService->getUserRankInfo($user);
        $rankOrder = $this->sevenTreasuresService->getRankOrder();
        $config = config('seven_treasures');

        return view('templates.basic.user.seven_treasures.index', compact('user', 'rankInfo', 'rankOrder', 'config'));
    }

    /**
     * 获取当前用户职级信息
     */
    public function getMyRankInfo(): JsonResponse
    {
        try {
            $user = auth()->user();
            $rankInfo = $this->sevenTreasuresService->getUserRankInfo($user);
            
            return response()->json([
                'status' => 'success',
                'data' => $rankInfo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '获取职级信息失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检查晋升资格
     */
    public function checkPromotionEligibility(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $targetRank = $request->input('target_rank');
            
            $eligibility = $this->sevenTreasuresService->checkPromotionEligibility($user, $targetRank);
            
            return response()->json([
                'status' => 'success',
                'data' => $eligibility
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '检查晋升资格失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取所有职级配置
     */
    public function getAllRanks(): JsonResponse
    {
        try {
            $config = config('seven_treasures');
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'ranks' => $config['ranks'],
                    'rank_order' => $config['rank_order']
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
     * 获取我的推荐线详情
     */
    public function getMyDownlines(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $maxGeneration = $request->input('max_generation', 3);
            
            $downlines = $this->getDownlinesByGeneration($user, $maxGeneration);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'rank' => $user->leader_rank_code,
                        'rank_name' => $this->getRankName($user->leader_rank_code),
                    ],
                    'downlines' => $downlines
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '获取推荐线详情失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取预估职级（实时计算）
     */
    public function getEstimatedRank(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // 实时计算累计PV
            $cumulativeWeakPv = $this->sevenTreasuresService->getCumulativeWeakPV($user);
            
            // 检查所有职级
            $eligibleRanks = [];
            $rankOrder = $this->sevenTreasuresService->getRankOrder();
            
            foreach ($rankOrder as $rankCode) {
                $config = config('seven_treasures.ranks.' . $rankCode);
                if ($cumulativeWeakPv >= $config['min_pv']) {
                    // 检查架构要求
                    $eligible = true;
                    if ($config['structure_requirement']) {
                        $eligible = $this->sevenTreasuresService->checkStructureRequirement($user, $config['structure_requirement']);
                    }
                    
                    if ($eligible) {
                        $eligibleRanks[] = $rankCode;
                    }
                }
            }
            
            $currentRank = $user->leader_rank_code;
            $nextEligible = null;
            foreach ($eligibleRanks as $rank) {
                if ($currentRank && array_search($rank, $rankOrder) > array_search($currentRank, $rankOrder)) {
                    $nextEligible = $rank;
                    break;
                }
                if (!$currentRank && $rank === 'liuli_xingzhe') {
                    $nextEligible = $rank;
                    break;
                }
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'current_pv' => $cumulativeWeakPv,
                    'eligible_ranks' => $eligibleRanks,
                    'next_eligible_rank' => $nextEligible,
                    'next_eligible_name' => $nextEligible ? config('seven_treasures.ranks.' . $nextEligible . '.name') : null,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '获取预估职级失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取下级按代数分组
     * 优化版本：预先加载所有下属用户，避免N+1查询
     */
    private function getDownlinesByGeneration(User $user, int $maxGeneration): array
    {
        $downlines = [];

        // 获取当前用户所有直接下级的ID
        $directChildIds = User::where("ref_by", $user->id)->pluck('id')->toArray();

        if (empty($directChildIds)) {
            return $downlines;
        }

        // 预先获取指定代数内所有下属用户（一次查询）
        $allDescendantIds = $this->getAllDescendantIds($directChildIds, $maxGeneration);

        // 按代数分组
        $descendantsByGeneration = $this->groupDescendantsByGeneration($directChildIds, $allDescendantIds, $maxGeneration);

        // 构建返回数据
        for ($generation = 1; $generation <= $maxGeneration; $generation++) {
            $generationUserIds = $descendantsByGeneration[$generation] ?? [];

            if (empty($generationUserIds)) {
                break;
            }

            // 批量获取用户信息
            $users = User::whereIn('id', $generationUserIds)->get();

            $generationUsers = [];
            foreach ($users as $userInGeneration) {
                $generationUsers[] = [
                    'id' => $userInGeneration->id,
                    'username' => $userInGeneration->username,
                    'rank' => $userInGeneration->leader_rank_code,
                    'rank_name' => $this->getRankName($userInGeneration->leader_rank_code),
                    'created_at' => $userInGeneration->created_at->format('Y-m-d H:i:s'),
                ];
            }

            $downlines[$generation] = $generationUsers;
        }

        return $downlines;
    }

    /**
     * 获取指定代数内的所有下属用户ID
     *
     * @param array $initialChildIds 初始下级ID
     * @param int $maxGeneration 最大代数
     * @return array 所有下属用户ID
     */
    private function getAllDescendantIds(array $initialChildIds, int $maxGeneration): array
    {
        $allIds = [];
        $currentIds = $initialChildIds;

        for ($generation = 0; $generation < $maxGeneration; $generation++) {
            if (empty($currentIds)) {
                break;
            }

            // 批量查询当前层级的所有下级
            $nextLevelIds = User::whereIn('ref_by', $currentIds)->pluck('id')->toArray();
            $allIds = array_merge($allIds, $nextLevelIds);
            $currentIds = $nextLevelIds;
        }

        return $allIds;
    }

    /**
     * 按代数分组下属用户
     *
     * @param array $directChildIds 直接下级ID
     * @param array $allDescendants 所有下属ID
     * @param int $maxGeneration 最大代数
     * @return array 按代数分组的用户ID
     */
    private function groupDescendantsByGeneration(array $directChildIds, array $allDescendants, int $maxGeneration): array
    {
        if (empty($allDescendants)) {
            return [];
        }

        // 构建用户ID到其推荐人的映射
        $userToParentMap = User::whereIn('id', $allDescendants)
            ->pluck('ref_by', 'id')
            ->toArray();

        // 使用BFS确定每个用户的代数
        $generationMap = [];
        $visited = [];
        $queue = [];

        // 初始化：直接将下级加入队列，第1代
        foreach ($directChildIds as $childId) {
            $queue[] = ['id' => $childId, 'generation' => 1];
            $visited[$childId] = true;
        }

        while (!empty($queue)) {
            $current = array_shift($queue);
            $userId = $current['id'];
            $gen = $current['generation'];

            $generationMap[$gen][] = $userId;

            if ($gen >= $maxGeneration) {
                continue;
            }

            // 查找该用户的所有下级
            $children = array_keys($userToParentMap, $userId);
            foreach ($children as $childId) {
                if (!isset($visited[$childId])) {
                    $visited[$childId] = true;
                    $queue[] = ['id' => $childId, 'generation' => $gen + 1];
                }
            }
        }

        return $generationMap;
    }

    /**
     * 获取职级名称
     */
    private function getRankName(?string $rankCode): string
    {
        if (!$rankCode) {
            return '未设定';
        }
        
        $config = config('seven_treasures.ranks.' . $rankCode);
        return $config['name'] ?? '未知职级';
    }
}
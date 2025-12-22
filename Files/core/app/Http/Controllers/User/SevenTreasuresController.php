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
     */
    private function getDownlinesByGeneration(User $user, int $maxGeneration): array
    {
        $downlines = [];
        
        // 从第1代开始
        $currentLevel = User::where("ref_by", $user->id)->get();
        
        for ($generation = 1; $generation <= $maxGeneration; $generation++) {
            $generationUsers = [];
            
            foreach ($currentLevel as $userInGeneration) {
                $generationUsers[] = [
                    'id' => $userInGeneration->id,
                    'username' => $userInGeneration->username,
                    'rank' => $userInGeneration->leader_rank_code,
                    'rank_name' => $this->getRankName($userInGeneration->leader_rank_code),
                    'created_at' => $userInGeneration->created_at->format('Y-m-d H:i:s'),
                ];
            }
            
            $downlines[$generation] = $generationUsers;
            
            // 准备下一代数据
            $nextLevel = [];
            foreach ($currentLevel as $parentUser) {
                $directReferrals = User::where("ref_by", $parentUser->id)->get();
                foreach ($directReferrals as $referral) {
                    $nextLevel[] = $referral;
                }
            }
            
            if (empty($nextLevel)) {
                break;
            }
            
            $currentLevel = $nextLevel;
        }
        
        return $downlines;
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
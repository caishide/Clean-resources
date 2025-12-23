<?php

namespace App\Services;

use App\Models\User;
use App\Models\PvLedger;
use App\Models\UserExtra;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SevenTreasuresService
{
    private const DEFAULT_CONFIG = [
        'ranks' => [],
        'rank_order' => [],
        'promotion' => [],
        'cache' => [],
    ];

    protected array $config;
    protected PVLedgerService $pvService;

    public function __construct()
    {
        $this->config = $this->resolveConfig();
        $this->pvService = app(PVLedgerService::class);
    }

    private function resolveConfig(): array
    {
        $config = config('seven_treasures');
        if (!is_array($config)) {
            $config = [];
        }

        return array_replace_recursive(self::DEFAULT_CONFIG, $config);
    }

    /**
     * 检查用户是否满足晋升条件
     *
     * @param User $user
     * @param string|null $targetRank 目标职级，为空时检查下一级
     * @return array 包含是否满足条件、详细信息等
     */
    public function checkPromotionEligibility(User $user, ?string $targetRank = null): array
    {
        if (empty($this->config['ranks']) || empty($this->config['rank_order'])) {
            return [
                'eligible' => false,
                'reason' => '七宝配置缺失',
                'current_rank' => $user->leader_rank_code,
                'target_rank' => null,
            ];
        }

        $currentRank = $user->leader_rank_code;
        $nextRank = $targetRank ?: $this->getNextRank($currentRank);
        
        if (!$nextRank) {
            return [
                'eligible' => false,
                'reason' => '已达到最高职级',
                'current_rank' => $currentRank,
                'target_rank' => $nextRank,
            ];
        }

        if (!isset($this->config['ranks'][$nextRank])) {
            return [
                'eligible' => false,
                'reason' => '职级配置不存在',
                'current_rank' => $currentRank,
                'target_rank' => $nextRank,
            ];
        }

        $rankConfig = $this->config['ranks'][$nextRank];
        
        // 检查PV要求
        $weakPv = $this->getCumulativeWeakPV($user);
        $pvRequirement = $rankConfig['min_pv'];
        $pvEligible = $weakPv >= $pvRequirement;

        // 检查直推要求
        $directRefsEligible = true;
        if ($rankConfig['required_direct_refs']) {
            $directCount = $this->getDirectReferralCount($user);
            $directRefsEligible = $directCount >= $rankConfig['required_direct_refs'];
        }

        // 检查架构要求
        $structureEligible = true;
        if ($rankConfig['structure_requirement']) {
            $structureEligible = $this->checkStructureRequirement($user, $rankConfig['structure_requirement']);
        }

        $allEligible = $pvEligible && $directRefsEligible && $structureEligible;

        return [
            'eligible' => $allEligible,
            'current_rank' => $currentRank,
            'target_rank' => $nextRank,
            'target_rank_name' => $rankConfig['name'],
            'requirements' => [
                'pv' => [
                    'required' => $pvRequirement,
                    'current' => $weakPv,
                    'eligible' => $pvEligible,
                    'shortage' => max(0, $pvRequirement - $weakPv),
                ],
                'direct_refs' => [
                    'required' => $rankConfig['required_direct_refs'],
                    'current' => $rankConfig['required_direct_refs'] ? $this->getDirectReferralCount($user) : null,
                    'eligible' => $directRefsEligible,
                ],
                'structure' => [
                    'requirement' => $rankConfig['structure_requirement'],
                    'eligible' => $structureEligible,
                    'details' => $rankConfig['structure_requirement'] ? $this->getStructureDetails($user, $rankConfig['structure_requirement']) : null,
                ],
            ],
        ];
    }

    /**
     * 执行用户职级晋升
     *
     * @param User $user
     * @param string $rankCode
     * @return bool
     */
    public function promoteUser(User $user, string $rankCode): bool
    {
        if (!isset($this->config['ranks'][$rankCode])) {
            Log::error("Invalid rank code: {$rankCode}");
            return false;
        }

        $rankConfig = $this->config['ranks'][$rankCode];

        try {
            DB::beginTransaction();

            // 更新用户职级
            $user->update([
                'leader_rank_code' => $rankCode,
                'leader_rank_multiplier' => $rankConfig['multiplier'],
            ]);

            // 记录职级晋升日志
            Log::channel('seven_treasures')->info("User {$user->id} promoted to {$rankCode}", [
                'user_id' => $user->id,
                'username' => $user->username,
                'from_rank' => $user->getOriginal('leader_rank_code'),
                'to_rank' => $rankCode,
                'rank_name' => $rankConfig['name'],
                'multiplier' => $rankConfig['multiplier'],
                'timestamp' => now(),
            ]);

            // 清除缓存
            $this->clearUserRankCache($user->id);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to promote user {$user->id} to {$rankCode}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取用户的累计小区PV
     *
     * @param User $user
     * @return float
     */
    public function getCumulativeWeakPV(User $user): float
    {
        $cacheKey = "user_{$user->id}_cumulative_weak_pv";
        
        return Cache::remember($cacheKey, 3600, function () use ($user) {
            $userExtra = $user->userExtra;
            if (!$userExtra) {
                return 0.0;
            }

            // 累计小区PV = 左右区累计PV的较小值
            $leftPv = $this->getCumulativePV($user, 1); // 左区
            $rightPv = $this->getCumulativePV($user, 2); // 右区
            
            return min($leftPv, $rightPv);
        });
    }

    /**
     * 获取用户的累计PV（按位置）
     *
     * @param User $user
     * @param int $position 1=左区, 2=右区
     * @return float
     */
    protected function getCumulativePV(User $user, int $position): float
    {
        return PvLedger::where('user_id', $user->id)
            ->where('position', $position)
            ->where('trx_type', '+')
            ->sum('amount');
    }

    /**
     * 获取直接推荐人数
     *
     * @param User $user
     * @return int
     */
    public function getDirectReferralCount(User $user): int
    {
        return User::where('ref_by', $user->id)->count();
    }

    /**
     * 检查架构要求
     *
     * @param User $user
     * @param array $requirement
     * @return bool
     */
    protected function checkStructureRequirement(User $user, array $requirement): bool
    {
        $requiredRank = $requirement['required_rank'];
        $requiredCount = $requirement['required_count'];
        $requiredLines = $requirement['lines'];

        // 获取用户的所有直接下级
        $directReferrals = User::where('ref_by', $user->id)->get();
        
        if ($directReferrals->count() < $requiredLines) {
            return false;
        }

        // 按推荐线分组
        $lines = [];
        foreach ($directReferrals as $referral) {
            $lines[$referral->id] = [$referral];
            
            // 递归查找该下级线下的所有人员
            $this->collectDownlineByRank($referral, $requiredRank, $lines[$referral->id]);
        }

        // 检查每条线是否满足要求
        $qualifiedLines = 0;
        foreach ($lines as $lineUsers) {
            $rankCount = 0;
            foreach ($lineUsers as $lineUser) {
                if ($lineUser->leader_rank_code === $requiredRank) {
                    $rankCount++;
                }
            }
            
            if ($rankCount >= 1) { // 每条线至少需要1个指定职级
                $qualifiedLines++;
            }
        }

        return $qualifiedLines >= $requiredLines;
    }

    /**
     * 收集下级指定职级的人员
     *
     * @param User $user
     * @param string $targetRank
     * @param array $downline
     * @return void
     */
    protected function collectDownlineByRank(User $user, string $targetRank, array &$downline): void
    {
        $directReferrals = User::where('ref_by', $user->id)->get();
        
        foreach ($directReferrals as $referral) {
            $downline[] = $referral;
            
            // 递归查找
            $this->collectDownlineByRank($referral, $targetRank, $downline);
        }
    }

    /**
     * 获取架构详情
     *
     * @param User $user
     * @param array $requirement
     * @return array
     */
    protected function getStructureDetails(User $user, array $requirement): array
    {
        $requiredRank = $requirement['required_rank'];
        $requiredCount = $requirement['required_count'];
        $requiredLines = $requirement['lines'];

        $directReferrals = User::where('ref_by', $user->id)->get();
        $lines = [];

        foreach ($directReferrals as $referral) {
            $lineUsers = [$referral];
            $this->collectDownlineByRank($referral, $requiredRank, $lineUsers);
            
            $rankCount = 0;
            foreach ($lineUsers as $lineUser) {
                if ($lineUser->leader_rank_code === $requiredRank) {
                    $rankCount++;
                }
            }
            
            $lines[] = [
                'root_user_id' => $referral->id,
                'root_username' => $referral->username,
                'total_members' => count($lineUsers),
                'rank_members' => $rankCount,
                'qualified' => $rankCount >= 1,
            ];
        }

        $qualifiedLines = array_filter($lines, fn($line) => $line['qualified']);
        
        return [
            'required_lines' => $requiredLines,
            'qualified_lines' => count($qualifiedLines),
            'lines' => $lines,
            'all_qualified' => count($qualifiedLines) >= $requiredLines,
        ];
    }

    /**
     * 获取下一级职级
     *
     * @param string|null $currentRank
     * @return string|null
     */
    public function getNextRank(?string $currentRank): ?string
    {
        if (empty($this->config['rank_order'])) {
            return null;
        }

        if (!$currentRank) {
            return 'liuli_xingzhe'; // 第一级
        }

        $order = $this->config['rank_order'];
        $currentIndex = array_search($currentRank, $order);
        
        if ($currentIndex === false || $currentIndex >= count($order) - 1) {
            return null; // 已是最高级
        }

        return $order[$currentIndex + 1];
    }

    /**
     * 获取职级顺序
     *
     * @return array
     */
    public function getRankOrder(): array
    {
        return $this->config['rank_order'];
    }

    /**
     * 清除用户职级缓存
     *
     * @param int $userId
     * @return void
     */
    protected function clearUserRankCache(int $userId): void
    {
        Cache::forget("user_{$userId}_cumulative_weak_pv");
        Cache::forget("user_{$userId}_rank_structure");
    }

    /**
     * 批量检查和晋升用户职级
     *
     * @return array 检查结果统计
     */
    public function batchPromotionCheck(): array
    {
        $stats = [
            'checked' => 0,
            'promoted' => 0,
            'errors' => 0,
        ];

        $users = User::whereNotNull('id')->cursor();
        
        foreach ($users as $user) {
            try {
                $stats['checked']++;
                
                $eligibility = $this->checkPromotionEligibility($user);
                
                if ($eligibility['eligible']) {
                    $nextRank = $eligibility['target_rank'];
                    if ($this->promoteUser($user, $nextRank)) {
                        $stats['promoted']++;
                    }
                }
                
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error("Error checking promotion for user {$user->id}: " . $e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * 获取用户当前职级信息
     *
     * @param User $user
     * @return array
     */
    public function getUserRankInfo(User $user): array
    {
        $currentRank = $user->leader_rank_code;
        $currentRankConfig = $currentRank && isset($this->config['ranks'][$currentRank])
            ? $this->config['ranks'][$currentRank]
            : null;
        $nextRankInfo = $this->checkPromotionEligibility($user);
        
        return [
            'current_rank' => $currentRank,
            'current_rank_name' => $currentRankConfig['name'] ?? '未设定',
            'current_multiplier' => $user->leader_rank_multiplier ?? 0,
            'next_rank' => $nextRankInfo['target_rank'] ?? null,
            'next_rank_name' => $nextRankInfo['target_rank_name'] ?? null,
            'promotion_eligible' => $nextRankInfo['eligible'] ?? false,
            'promotion_details' => $nextRankInfo['requirements'] ?? [],
        ];
    }
}

<?php

namespace App\Services\CarryFlash;

use App\Services\PVLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * 扣除弱区 PV 结转策略
 * 
 * 扣除弱区全部 PV
 */
class DeductWeakStrategy implements CarryFlashStrategy
{
    public function __construct(
        private PVLedgerService $pvService
    ) {}
    
    /**
     * 处理 PV 结转
     * 
     * @param string $weekKey 周键
     * @param array $userSummaries 用户结算汇总数据
     * @return void
     */
    public function process(string $weekKey, array $userSummaries): void
    {
        foreach ($userSummaries as $summary) {
            $userId = $summary['user_id'];
            $weakPV = (float) ($summary['weak_pv'] ?? 0);
            $leftPV = (float) ($summary['left_pv'] ?? 0);
            $rightPV = (float) ($summary['right_pv'] ?? 0);
            $leftEnd = $leftPV;
            $rightEnd = $rightPV;
            
            if ($weakPV > 0) {
                $position = $leftPV <= $rightPV ? 1 : 2;
                
                if ($position === 1) {
                    $leftEnd = $leftPV - $weakPV;
                } else {
                    $rightEnd = $rightPV - $weakPV;
                }
                
                $this->pvService->creditCarryFlash(
                    $userId,
                    $position,
                    $weakPV,
                    $weekKey,
                    "结转-扣除弱区PV",
                    "carry_flash_deduct_weak"
                );
            }
            
            // 更新用户汇总
            $this->updateUserSummary($weekKey, $userId, $leftPV, $rightPV, $leftEnd, $rightEnd);
        }
    }
    
    /**
     * 更新用户汇总
     * 
     * @param string $weekKey 周键
     * @param int $userId 用户ID
     * @param float $leftPV 左区PV
     * @param float $rightPV 右区PV
     * @param float $leftEnd 左区结束PV
     * @param float $rightEnd 右区结束PV
     * @return void
     */
    private function updateUserSummary(
        string $weekKey,
        int $userId,
        float $leftPV,
        float $rightPV,
        float $leftEnd,
        float $rightEnd
    ): void {
        if ($leftEnd != $leftPV || $rightEnd != $rightPV) {
            DB::table('weekly_settlement_user_summaries')
                ->where('week_key', $weekKey)
                ->where('user_id', $userId)
                ->update([
                    'left_pv_end' => $leftEnd,
                    'right_pv_end' => $rightEnd,
                    'updated_at' => now(),
                ]);
        }
    }
}
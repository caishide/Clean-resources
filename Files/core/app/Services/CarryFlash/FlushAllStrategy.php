<?php

namespace App\Services\CarryFlash;

use App\Services\PVLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * 清空全部 PV 结转策略
 * 
 * 清空左右区全部 PV
 */
class FlushAllStrategy implements CarryFlashStrategy
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
            $leftPV = (float) ($summary['left_pv'] ?? 0);
            $rightPV = (float) ($summary['right_pv'] ?? 0);
            $leftEnd = $leftPV;
            $rightEnd = $rightPV;
            
            // 左区清零
            if ($leftPV != 0) {
                $leftEnd = 0.0;
                $trxType = $leftPV > 0 ? '-' : '+';
                
                $this->pvService->creditCarryFlash(
                    $userId,
                    1,
                    abs($leftPV),
                    $weekKey,
                    "结转-清空左区PV",
                    "carry_flash_flush_all",
                    $trxType
                );
            }
            
            // 右区清零
            if ($rightPV != 0) {
                $rightEnd = 0.0;
                $trxType = $rightPV > 0 ? '-' : '+';
                
                $this->pvService->creditCarryFlash(
                    $userId,
                    2,
                    abs($rightPV),
                    $weekKey,
                    "结转-清空右区PV",
                    "carry_flash_flush_all",
                    $trxType
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
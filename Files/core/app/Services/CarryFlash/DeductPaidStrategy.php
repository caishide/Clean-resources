<?php

namespace App\Services\CarryFlash;

use App\Services\PVLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * 扣除已发放 PV 结转策略
 * 
 * 发放了多少对碰奖,就从左右区同时扣除相应比例的 PV
 */
class DeductPaidStrategy implements CarryFlashStrategy
{
    public function __construct(
        private PVLedgerService $pvService,
        private float $pairPvUnit,
        private float $pairUnitAmount
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
            $pairPaidActual = (float) ($summary['pair_paid_actual'] ?? 0);
            $weakPV = (float) ($summary['weak_pv'] ?? 0);
            $leftPV = (float) ($summary['left_pv'] ?? 0);
            $rightPV = (float) ($summary['right_pv'] ?? 0);
            $leftEnd = $leftPV;
            $rightEnd = $rightPV;
            
            $capAmount = (float) ($summary['cap_amount'] ?? 0);
            $capPV = (float) ($summary['cap_pv'] ?? 0);
            $weakPosition = $leftPV <= $rightPV ? 1 : 2;
            
            // 扣除已发放 PV
            if ($pairPaidActual > 0 && $weakPV > 0 && $this->pairUnitAmount > 0) {
                // 计算需要扣除的 PV 金额
                $deductPV = $pairPaidActual * ($this->pairPvUnit / $this->pairUnitAmount);
                $deductPV = min($deductPV, $weakPV);
                
                if ($deductPV > 0) {
                    $leftEnd = $leftPV - $deductPV;
                    $rightEnd = $rightPV - $deductPV;
                    
                    $this->pvService->creditCarryFlash(
                        $userId,
                        1,
                        $deductPV,
                        $weekKey,
                        "结转-扣除已发放PV({$pairPaidActual})",
                        "carry_flash_deduct_paid"
                    );
                    
                    $this->pvService->creditCarryFlash(
                        $userId,
                        2,
                        $deductPV,
                        $weekKey,
                        "结转-扣除已发放PV({$pairPaidActual})",
                        "carry_flash_deduct_paid"
                    );
                }
            }
            
            // 若触发封顶,弱区超出封顶的 PV 归零
            if ($capAmount > 0 && $this->pairUnitAmount > 0 && $weakPV > $capPV) {
                $excessWeakPV = $weakPV - $capPV;
                if ($excessWeakPV > 0) {
                    if ($weakPosition === 1) {
                        $leftEnd -= $excessWeakPV;
                    } else {
                        $rightEnd -= $excessWeakPV;
                    }
                    
                    $this->pvService->creditCarryFlash(
                        $userId,
                        $weakPosition,
                        $excessWeakPV,
                        $weekKey,
                        "结转-封顶超额PV",
                        "carry_flash_cap_excess"
                    );
                }
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
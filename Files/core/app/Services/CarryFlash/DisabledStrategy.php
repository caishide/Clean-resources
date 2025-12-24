<?php

namespace App\Services\CarryFlash;

/**
 * 禁用结转策略
 * 
 * 不执行任何结转操作，保留所有 PV
 */
class DisabledStrategy implements CarryFlashStrategyInterface
{
    /**
     * 处理结转（不执行任何操作）
     */
    public function process(string $weekKey, array $userSummary): array
    {
        $leftPV = (float) ($userSummary['left_pv'] ?? 0);
        $rightPV = (float) ($userSummary['right_pv'] ?? 0);

        // 不执行任何结转操作，直接返回当前 PV
        return [
            'left_end' => $leftPV,
            'right_end' => $rightPV,
        ];
    }
}
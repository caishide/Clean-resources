<?php

namespace App\Services\CarryFlash;

/**
 * PV 结转策略接口
 * 
 * 定义结转策略的统一接口
 */
interface CarryFlashStrategyInterface
{
    /**
     * 处理 PV 结转
     *
     * @param string $weekKey 周键
     * @param array $userSummary 用户结算汇总
     * @return array 处理结果
     */
    public function process(string $weekKey, array $userSummary): array;
}
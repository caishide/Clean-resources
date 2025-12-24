<?php

namespace App\Services\CarryFlash;

/**
 * PV 结转策略接口
 * 
 * 定义了 PV 结转处理的统一接口
 */
interface CarryFlashStrategy
{
    /**
     * 处理 PV 结转
     * 
     * @param string $weekKey 周键
     * @param array $userSummaries 用户结算汇总数据
     * @return void
     */
    public function process(string $weekKey, array $userSummaries): void;
}
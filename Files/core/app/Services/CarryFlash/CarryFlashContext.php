<?php

namespace App\Services\CarryFlash;

use Illuminate\Container\Container;

/**
 * PV 结转策略上下文
 * 
 * 负责根据结转模式选择并执行相应的策略
 */
class CarryFlashContext
{
    /**
     * 策略映射表
     * 
     * @var array
     */
    private array $strategies = [
        1 => DeductPaidStrategy::class,   // 扣除已发放 PV
        2 => DeductWeakStrategy::class,   // 扣除弱区 PV
        3 => FlushAllStrategy::class,     // 清空全部 PV
    ];
    
    /**
     * 构造函数
     * 
     * @param Container $container 服务容器
     */
    public function __construct(
        private Container $container
    ) {}
    
    /**
     * 执行结转策略
     * 
     * @param int $mode 结转模式
     * @param string $weekKey 周键
     * @param array $userSummaries 用户结算汇总数据
     * @return void
     */
    public function execute(int $mode, string $weekKey, array $userSummaries): void
    {
        if (!isset($this->strategies[$mode])) {
            return;
        }
        
        $strategyClass = $this->strategies[$mode];
        $strategy = $this->container->make($strategyClass);
        
        $strategy->process($weekKey, $userSummaries);
    }
    
    /**
     * 检查模式是否有效
     * 
     * @param int $mode 结转模式
     * @return bool
     */
    public function isValidMode(int $mode): bool
    {
        return isset($this->strategies[$mode]);
    }
    
    /**
     * 获取所有支持的策略
     * 
     * @return array
     */
    public function getSupportedStrategies(): array
    {
        return array_keys($this->strategies);
    }
}
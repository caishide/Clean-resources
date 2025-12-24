<?php

namespace App\Services\CarryFlash;

use App\Services\PVLedgerService;
use InvalidArgumentException;

/**
 * 结转策略工厂类
 * 
 * 根据配置创建相应的结转策略实例
 */
class CarryFlashStrategyFactory
{
    private PVLedgerService $pvService;

    // 策略类型常量
    public const STRATEGY_DEDUCT_PAID = 'deduct_paid';
    public const STRATEGY_DEDUCT_WEAK = 'deduct_weak';
    public const STRATEGY_FLUSH_ALL = 'flush_all';
    public const STRATEGY_DISABLED = 'disabled';

    public function __construct(PVLedgerService $pvService)
    {
        $this->pvService = $pvService;
    }

    /**
     * 根据策略类型创建策略实例
     * 
     * @param string $strategyType 策略类型
     * @return CarryFlashStrategyInterface
     * @throws InvalidArgumentException
     */
    public function create(string $strategyType): CarryFlashStrategyInterface
    {
        return match ($strategyType) {
            self::STRATEGY_DEDUCT_PAID => new DeductPaidStrategy($this->pvService),
            self::STRATEGY_DEDUCT_WEAK => new DeductWeakStrategy($this->pvService),
            self::STRATEGY_FLUSH_ALL => new FlushAllStrategy($this->pvService),
            self::STRATEGY_DISABLED => new DisabledStrategy(),
            default => throw new InvalidArgumentException("不支持的结转策略类型: {$strategyType}"),
        };
    }

    /**
     * 从系统配置获取策略类型并创建策略实例
     * 
     * @return CarryFlashStrategyInterface
     */
    public function createFromConfig(): CarryFlashStrategyInterface
    {
        $strategyType = config('settlement.carry_flash_strategy', self::STRATEGY_DISABLED);
        return $this->create($strategyType);
    }

    /**
     * 获取所有可用的策略类型
     * 
     * @return array
     */
    public static function getAvailableStrategies(): array
    {
        return [
            self::STRATEGY_DEDUCT_PAID => '扣除已结算 PV',
            self::STRATEGY_DEDUCT_WEAK => '扣除弱区 PV',
            self::STRATEGY_FLUSH_ALL => '清空全部 PV',
            self::STRATEGY_DISABLED => '禁用结转',
        ];
    }

    /**
     * 验证策略类型是否有效
     * 
     * @param string $strategyType
     * @return bool
     */
    public static function isValidStrategy(string $strategyType): bool
    {
        return in_array($strategyType, [
            self::STRATEGY_DEDUCT_PAID,
            self::STRATEGY_DEDUCT_WEAK,
            self::STRATEGY_FLUSH_ALL,
            self::STRATEGY_DISABLED,
        ]);
    }
}
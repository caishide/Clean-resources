<?php

namespace App\Services;

use App\Models\User;
use App\Models\PvLedger;
use App\Models\WeeklySettlement;
use App\Models\QuarterlySettlement;
use App\Services\CarryFlash\CarryFlashContext;
use App\Services\CarryFlash\CarryFlashStrategyFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 结算服务类（使用策略模式重构）
 * 
 * 负责处理周结算和季度结算逻辑
 */
class SettlementServiceWithStrategy
{
    private PVLedgerService $pvService;
    private CarryFlashContext $carryFlashContext;
    private CarryFlashStrategyFactory $strategyFactory;

    public function __construct(
        PVLedgerService $pvService,
        CarryFlashContext $carryFlashContext,
        CarryFlashStrategyFactory $strategyFactory
    ) {
        $this->pvService = $pvService;
        $this->carryFlashContext = $carryFlashContext;
        $this->strategyFactory = $strategyFactory;
    }

    /**
     * 执行周结算
     * 
     * @param string $weekKey 周期键，格式: YYYY-Www (例如: 2025-W01)
     * @return array
     */
    public function executeWeeklySettlement(string $weekKey): array
    {
        Log::info("开始执行周结算", ['week_key' => $weekKey]);

        try {
            DB::beginTransaction();

            // 1. 验证周期键格式
            $this->validateWeekKey($weekKey);

            // 2. 检查是否已结算
            $this->checkIfAlreadySettled($weekKey);

            // 3. 获取所有用户及其 PV 汇总
            $userSummaries = $this->getUserSummaries();

            // 4. 设置结转策略
            $strategy = $this->strategyFactory->createFromConfig();
            $this->carryFlashContext->setStrategy($strategy);

            // 5. 执行结转
            $carryFlashResults = $this->carryFlashContext->executeBatch($weekKey, $userSummaries);

            // 6. 计算奖金
            $bonusResults = $this->calculateBonuses($userSummaries, $weekKey);

            // 7. 创建结算记录
            $settlementRecords = $this->createSettlementRecords($userSummaries, $bonusResults, $weekKey);

            DB::commit();

            Log::info("周结算完成", [
                'week_key' => $weekKey,
                'users_processed' => count($userSummaries),
                'carry_flash_errors' => count($carryFlashResults['errors']),
            ]);

            return [
                'success' => true,
                'week_key' => $weekKey,
                'users_processed' => count($userSummaries),
                'carry_flash_errors' => $carryFlashResults['errors'],
                'settlement_records' => $settlementRecords,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("周结算失败", [
                'week_key' => $weekKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 验证周期键格式
     */
    private function validateWeekKey(string $weekKey): void
    {
        if (!preg_match('/^\d{4}-W\d{2}$/', $weekKey)) {
            throw new \InvalidArgumentException("周期键格式无效，应为 YYYY-Www 格式");
        }
    }

    /**
     * 检查是否已结算
     */
    private function checkIfAlreadySettled(string $weekKey): void
    {
        $exists = WeeklySettlement::where('week_key', $weekKey)->exists();
        if ($exists) {
            throw new \RuntimeException("周期 {$weekKey} 已经结算过了");
        }
    }

    /**
     * 获取所有用户及其 PV 汇总
     */
    private function getUserSummaries(): array
    {
        $users = User::where('status', 1)->get();
        $summaries = [];

        foreach ($users as $user) {
            $leftPV = PvLedger::where('user_id', $user->id)
                ->where('position', 1)
                ->sum('pv_amount');

            $rightPV = PvLedger::where('user_id', $user->id)
                ->where('position', 2)
                ->sum('pv_amount');

            $summaries[] = [
                'user_id' => $user->id,
                'left_pv' => (float) $leftPV,
                'right_pv' => (float) $rightPV,
                'weak_pv' => min($leftPV, $rightPV),
            ];
        }

        return $summaries;
    }

    /**
     * 计算奖金
     */
    private function calculateBonuses(array $userSummaries, string $weekKey): array
    {
        $bonusResults = [];

        foreach ($userSummaries as $summary) {
            $userId = $summary['user_id'];
            $leftPV = $summary['left_pv'];
            $rightPV = $summary['right_pv'];
            $weakPV = min($leftPV, $rightPV);

            // 计算对碰奖（假设为弱区 PV 的 10%）
            $bonus = $weakPV * 0.1;

            $bonusResults[$userId] = [
                'weak_pv' => $weakPV,
                'bonus' => $bonus,
            ];
        }

        return $bonusResults;
    }

    /**
     * 创建结算记录
     */
    private function createSettlementRecords(
        array $userSummaries,
        array $bonusResults,
        string $weekKey
    ): array {
        $records = [];

        foreach ($userSummaries as $summary) {
            $userId = $summary['user_id'];
            $bonus = $bonusResults[$userId] ?? null;

            if (!$bonus) {
                continue;
            }

            $record = WeeklySettlement::create([
                'user_id' => $userId,
                'week_key' => $weekKey,
                'left_pv' => $summary['left_pv'],
                'right_pv' => $summary['right_pv'],
                'weak_pv' => $bonus['weak_pv'],
                'bonus_amount' => $bonus['bonus'],
                'settlement_date' => now(),
            ]);

            $records[] = $record;
        }

        return $records;
    }

    /**
     * 执行季度结算
     * 
     * @param string $quarterKey 季度键，格式: YYYY-Qq (例如: 2025-Q1)
     * @return array
     */
    public function executeQuarterlySettlement(string $quarterKey): array
    {
        Log::info("开始执行季度结算", ['quarter_key' => $quarterKey]);

        try {
            DB::beginTransaction();

            // 1. 验证季度键格式
            $this->validateQuarterKey($quarterKey);

            // 2. 检查是否已结算
            $this->checkIfQuarterlyAlreadySettled($quarterKey);

            // 3. 获取该季度的所有周结算
            $weeklySettlements = $this->getWeeklySettlementsByQuarter($quarterKey);

            // 4. 汇总季度数据
            $quarterlySummaries = $this->aggregateQuarterlyData($weeklySettlements);

            // 5. 计算季度奖金
            $quarterlyBonuses = $this->calculateQuarterlyBonuses($quarterlySummaries);

            // 6. 创建季度结算记录
            $settlementRecords = $this->createQuarterlySettlementRecords(
                $quarterlySummaries,
                $quarterlyBonuses,
                $quarterKey
            );

            DB::commit();

            Log::info("季度结算完成", [
                'quarter_key' => $quarterKey,
                'users_processed' => count($quarterlySummaries),
            ]);

            return [
                'success' => true,
                'quarter_key' => $quarterKey,
                'users_processed' => count($quarterlySummaries),
                'settlement_records' => $settlementRecords,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("季度结算失败", [
                'quarter_key' => $quarterKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 验证季度键格式
     */
    private function validateQuarterKey(string $quarterKey): void
    {
        if (!preg_match('/^\d{4}-Q[1-4]$/', $quarterKey)) {
            throw new \InvalidArgumentException("季度键格式无效，应为 YYYY-Qq 格式");
        }
    }

    /**
     * 检查季度是否已结算
     */
    private function checkIfQuarterlyAlreadySettled(string $quarterKey): void
    {
        $exists = QuarterlySettlement::where('quarter_key', $quarterKey)->exists();
        if ($exists) {
            throw new \RuntimeException("季度 {$quarterKey} 已经结算过了");
        }
    }

    /**
     * 获取指定季度的所有周结算
     */
    private function getWeeklySettlementsByQuarter(string $quarterKey): array
    {
        preg_match('/^(\d{4})-Q(\d)$/', $quarterKey, $matches);
        $year = $matches[1];
        $quarter = (int) $matches[2];

        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $startMonth + 2;

        return WeeklySettlement::whereYear('settlement_date', $year)
            ->whereMonth('settlement_date', '>=', $startMonth)
            ->whereMonth('settlement_date', '<=', $endMonth)
            ->get()
            ->groupBy('user_id')
            ->toArray();
    }

    /**
     * 汇总季度数据
     */
    private function aggregateQuarterlyData(array $weeklySettlements): array
    {
        $summaries = [];

        foreach ($weeklySettlements as $userId => $settlements) {
            $totalLeftPV = 0;
            $totalRightPV = 0;
            $totalWeakPV = 0;
            $totalBonus = 0;

            foreach ($settlements as $settlement) {
                $totalLeftPV += $settlement['left_pv'];
                $totalRightPV += $settlement['right_pv'];
                $totalWeakPV += $settlement['weak_pv'];
                $totalBonus += $settlement['bonus_amount'];
            }

            $summaries[$userId] = [
                'user_id' => $userId,
                'total_left_pv' => $totalLeftPV,
                'total_right_pv' => $totalRightPV,
                'total_weak_pv' => $totalWeakPV,
                'total_bonus' => $totalBonus,
            ];
        }

        return $summaries;
    }

    /**
     * 计算季度奖金
     */
    private function calculateQuarterlyBonuses(array $quarterlySummaries): array
    {
        $bonuses = [];

        foreach ($quarterlySummaries as $userId => $summary) {
            // 季度额外奖金（假设为总奖金的 5%）
            $extraBonus = $summary['total_bonus'] * 0.05;

            $bonuses[$userId] = [
                'total_bonus' => $summary['total_bonus'],
                'extra_bonus' => $extraBonus,
                'final_bonus' => $summary['total_bonus'] + $extraBonus,
            ];
        }

        return $bonuses;
    }

    /**
     * 创建季度结算记录
     */
    private function createQuarterlySettlementRecords(
        array $quarterlySummaries,
        array $quarterlyBonuses,
        string $quarterKey
    ): array {
        $records = [];

        foreach ($quarterlySummaries as $userId => $summary) {
            $bonus = $quarterlyBonuses[$userId] ?? null;

            if (!$bonus) {
                continue;
            }

            $record = QuarterlySettlement::create([
                'user_id' => $userId,
                'quarter_key' => $quarterKey,
                'total_left_pv' => $summary['total_left_pv'],
                'total_right_pv' => $summary['total_right_pv'],
                'total_weak_pv' => $summary['total_weak_pv'],
                'total_bonus' => $bonus['total_bonus'],
                'extra_bonus' => $bonus['extra_bonus'],
                'final_bonus' => $bonus['final_bonus'],
                'settlement_date' => now(),
            ]);

            $records[] = $record;
        }

        return $records;
    }
}
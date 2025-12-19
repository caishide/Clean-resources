<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\BonusService;
use App\Services\PVLedgerService;
use App\Services\PointsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Sentry\Laravel\Facade as Sentry;

/**
 * 订单观察者
 * 监听订单状态变化，自动触发奖金和PV流程
 */
class OrderObserver
{
    protected BonusService $bonusService;
    protected PVLedgerService $pvLedgerService;
    protected PointsService $pointsService;

    public function __construct(
        BonusService $bonusService,
        PVLedgerService $pvLedgerService,
        PointsService $pointsService
    ) {
        $this->bonusService = $bonusService;
        $this->pvLedgerService = $pvLedgerService;
        $this->pointsService = $pointsService;
    }

    /**
     * 订单创建事件
     */
    public function created(Order $order): void
    {
        Log::info('OrderObserver: Order created', ['order_id' => $order->id, 'trx' => $order->trx]);
    }

    /**
     * 订单更新事件
     * 主要监听status从0变为1(发货)的情况
     */
    public function updated(Order $order): void
    {
        // 检查状态是否从pending(0)变为shipped(1)
        if ($order->wasChanged('status') && $order->status == 1 && $order->getOriginal('status') == 0) {
            $this->handleOrderShipped($order);
        }
    }

    /**
     * 处理订单发货事件
     * 触发完整的奖金和PV流程
     */
    protected function handleOrderShipped(Order $order): void
    {
        Log::info('OrderObserver: Processing shipped order', [
            'order_id' => $order->id,
            'trx' => $order->trx,
            'user_id' => $order->user_id,
            'quantity' => $order->quantity
        ]);

        try {
            DB::transaction(function () use ($order) {
                // 1. PV累加到上线树
                $this->processPVAccumulation($order);

                // 2. 直推奖发放
                $this->processDirectBonus($order);

                // 3. 层碰奖检测(仅标记，周结算时计算)
                $this->processLevelPairDetection($order);

                // 4. 莲子积分发放
                $this->processPointsCredit($order);

                // 5. 用户激活状态更新
                $this->processUserActivation($order);

                Log::info('OrderObserver: Order processing completed', ['order_id' => $order->id]);
            });
        } catch (\Exception $e) {
            Log::error('OrderObserver: Order processing failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            if (class_exists(\Sentry\Laravel\Facade::class)) {
                Sentry::captureException($e);
            }
            throw $e;
        }
    }

    /**
     * PV累加到上线树
     */
    protected function processPVAccumulation(Order $order): void
    {
        $pvAmount = $order->quantity * 3000; // 每台3000PV
        $user = $order->user;
        $result = $this->pvLedgerService->creditPV($order);

        Log::info('OrderObserver: PV accumulated', [
            'order_id' => $order->id,
            'pv_amount' => $pvAmount,
            'affected_users' => count($result)
        ]);
    }

    /**
     * 直推奖发放
     */
    protected function processDirectBonus(Order $order): void
    {
        $result = $this->bonusService->issueDirectBonus($order);

        Log::info('OrderObserver: Direct bonus processed', [
            'order_id' => $order->id,
            'result' => $result
        ]);
    }

    /**
     * 层碰奖检测
     * 检测是否触发层碰，标记到user_level_hits表
     */
    protected function processLevelPairDetection(Order $order): void
    {
        $result = $this->bonusService->detectLevelPairHits($order);

        Log::info('OrderObserver: Level pair detection completed', [
            'order_id' => $order->id,
            'hits_detected' => $result['hits_count'] ?? 0
        ]);
    }

    /**
     * 莲子积分发放
     */
    protected function processPointsCredit(Order $order): void
    {
        $user = $order->user;
        $sponsor = $user->sponsor;

        // A类：自购积分
        $selfPoints = $this->pointsService->creditSelfPurchase(
            $user->id,
            $order->quantity,
            $order->trx
        );

        Log::info('OrderObserver: Self purchase points credited', [
            'user_id' => $user->id,
            'points' => $selfPoints['points'] ?? 0
        ]);

        // B类：直推积分（给推荐人）
        if ($sponsor) {
            $directPoints = $this->pointsService->creditDirectReferral(
                $sponsor->id,
                $order->quantity,
                $order->trx
            );

            Log::info('OrderObserver: Direct referral points credited', [
                'sponsor_id' => $sponsor->id,
                'points' => $directPoints['points'] ?? 0
            ]);
        }
    }

    /**
     * 用户激活状态更新
     */
    protected function processUserActivation(Order $order): void
    {
        $user = $order->user;

        // 累计请购数量与活跃时间
        $user->personal_purchase_count = ($user->personal_purchase_count ?? 0) + $order->quantity;
        $user->last_activity_date = now()->toDateString();
        $user->rank_level = $this->computeRankLevel($user->personal_purchase_count);

        // 检查用户是否需要激活
        if ($user->plan_id == 0) {
            $user->plan_id = 1; // 激活
        }
        $user->save();

        // 触发激活事件 - 释放待处理奖金
        $this->bonusService->releasePendingBonuses($user->id);

        Log::info('OrderObserver: User activated/updated', ['user_id' => $user->id]);
    }

    /**
     * 计算身份等级
     */
    private function computeRankLevel(int $purchaseCount): int
    {
        if ($purchaseCount >= 10) {
            return 3; // 高级
        }
        if ($purchaseCount >= 3) {
            return 2; // 中级
        }
        if ($purchaseCount >= 1) {
            return 1; // 初级
        }
        return 0;
    }
}

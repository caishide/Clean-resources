<?php

namespace App\Services;

use App\Constants\Status;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderShipmentService
{
    public function __construct(
        protected PVLedgerService $pvLedgerService,
        protected BonusService $bonusService,
        protected PointsService $pointsService,
        protected AdjustmentService $adjustmentService,
    ) {}

    /**
     * 后台发货：触发 PV 入账、直推/层碰、莲子积分、自购计数与激活（幂等）。
     */
    public function ship(Order $order): array
    {
        if ((int) $order->status === Status::ORDER_SHIPPED) {
            return ['status' => 'noop', 'message' => 'Order already shipped'];
        }

        if ((int) $order->status !== Status::ORDER_PENDING) {
            return ['status' => 'error', 'message' => 'Order is not shippable'];
        }

        return DB::transaction(function () use ($order) {
            $order->status = Status::ORDER_SHIPPED;
            $order->save();

            $order->loadMissing(['user', 'product']);
            $buyer = $order->user;

            $pvResults = $this->pvLedgerService->creditPVFromOrder($order);

            $directResult = $this->bonusService->issueDirectBonus($order);
            $levelPairResult = $this->bonusService->triggerLevelPairBonus($order);

            // 莲子积分：自购 + 直推（P1）
            $this->pointsService->creditSelfPurchase($buyer->id, (int) $order->quantity, $order->trx);
            if (!empty($buyer->ref_by)) {
                $this->pointsService->creditDirectReferral((int) $buyer->ref_by, (int) $order->quantity, $order->trx);
            }

            // 自购累计与等级（激活：rank_level >= 1）
            $buyerFresh = User::where('id', $buyer->id)->lockForUpdate()->first();
            $oldRank = (int) ($buyerFresh->rank_level ?? 0);
            $buyerFresh->personal_purchase_count = (int) ($buyerFresh->personal_purchase_count ?? 0) + (int) $order->quantity;
            $buyerFresh->last_activity_date = now()->toDateString();
            $buyerFresh->rank_level = max($oldRank, $this->resolveRankLevel((int) $buyerFresh->personal_purchase_count));
            $buyerFresh->save();

            // 直推活跃：直推 1 单也视为活跃
            if (!empty($buyerFresh->ref_by)) {
                User::where('id', (int) $buyerFresh->ref_by)->update([
                    'last_activity_date' => now()->toDateString(),
                ]);
            }

            $released = [];
            if ($oldRank < 1 && (int) $buyerFresh->rank_level >= 1) {
                $released = $this->bonusService->releasePendingBonusesOnActivation($buyerFresh->id);
            }

            return [
                'status' => 'success',
                'pv_rows' => count($pvResults),
                'direct' => $directResult,
                'level_pair' => $levelPairResult,
                'released_pending' => $released,
            ];
        });
    }

    private function resolveRankLevel(int $personalPurchaseCount): int
    {
        if ($personalPurchaseCount >= 10) {
            return 3;
        }
        if ($personalPurchaseCount >= 3) {
            return 2;
        }
        if ($personalPurchaseCount >= 1) {
            return 1;
        }
        return 0;
    }

    /**
     * 发货后退款：创建调整批次并冲正PV/奖金/莲子。
     */
    public function refund(Order $order, string $reason = 'refund'): array
    {
        if ((int) $order->status !== Status::ORDER_SHIPPED) {
            return ['status' => 'error', 'message' => 'Only shipped orders can be refunded'];
        }

        return DB::transaction(function () use ($order, $reason) {
            $order->status = Status::ORDER_CANCELED;
            $order->save();

            $batch = $this->adjustmentService->createRefundAdjustment($order, $reason);

            return [
                'status' => 'success',
                'batch_id' => $batch->id,
                'batch_key' => $batch->batch_key,
                'finalized_at' => $batch->finalized_at,
            ];
        });
    }
}

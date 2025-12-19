<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\PendingBonus;
use App\Models\Transaction;
use App\Models\UserLevelHit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BonusService
{
    protected BonusConfigService $bonusConfigService;
    protected array $config = [];
    protected float $pvUnit = 3000;

    public function __construct(BonusConfigService $bonusConfigService)
    {
        $this->bonusConfigService = $bonusConfigService;
        $this->config = $bonusConfigService->get();
    }

    private function isActivated(User $user): bool
    {
        // V10.1 口径：rank_level >= 1 视为激活；兼容旧库用 plan_id 判断
        $rankLevel = $user->rank_level ?? null;
        if ($rankLevel !== null) {
            return (int) $rankLevel >= 1;
        }

        return (int) ($user->plan_id ?? 0) >= 1;
    }

    /**
     * 发放直推奖（配置 direct_rate，默认20%）
     *
     * @param Order $order 订单
     * @return array 结果数组 ['status' => 'success|error', 'type' => 'paid|pending', 'amount' => float]
     */
    public function issueDirectBonus(Order $order): array
    {
        $buyer = User::find($order->user_id);
        if (!$buyer) {
            return ['status' => 'error', 'message' => 'Buyer not found'];
        }

        $sponsor = User::find($buyer->ref_by);
        if (!$sponsor) {
            return ['status' => 'error', 'message' => 'Sponsor not found'];
        }

        $directRate = $this->config['direct_rate'] ?? 0.20;
        $bonusAmount = ($order->quantity * $this->pvUnit) * $directRate;

        // 幂等：若该订单已发放直推或已入待处理则跳过
        $existingTrx = Transaction::where('user_id', $sponsor->id)
            ->where('remark', 'direct_bonus')
            ->where('source_type', 'order')
            ->where('source_id', $order->trx)
            ->exists();
        $existingPending = PendingBonus::where('recipient_id', $sponsor->id)
            ->where('bonus_type', 'direct')
            ->where('source_type', 'order')
            ->where('source_id', $order->trx)
            ->exists();
        if ($existingTrx || $existingPending) {
            return ['status' => 'success', 'type' => 'noop', 'amount' => $bonusAmount];
        }

        // 检查收款人是否已激活（V10.1：rank_level >= 1）
        if ($this->isActivated($sponsor)) {
            // 已激活：直接入账
            $newBalance = $sponsor->balance + $bonusAmount;
            $sponsor->balance = $newBalance;
            $sponsor->save();

            Transaction::create([
                'user_id' => $sponsor->id,
                'trx_type' => '+',
                'amount' => $bonusAmount,
                'charge' => 0,
                'post_balance' => $newBalance,
                'remark' => 'direct_bonus',
                'source_type' => 'order',
                'source_id' => $order->trx,
                'details' => json_encode(['order_id' => $order->id])
            ]);

            return ['status' => 'success', 'type' => 'paid', 'amount' => $bonusAmount];
        } else {
            // 未激活：进入待处理
            PendingBonus::updateOrCreate(
                [
                    'recipient_id' => $sponsor->id,
                    'bonus_type' => 'direct',
                    'source_type' => 'order',
                    'source_id' => $order->trx,
                ],
                [
                    'amount' => $bonusAmount,
                    'accrued_week_key' => $this->getCurrentWeekKey(),
                    'status' => 'pending',
                    'release_mode' => 'auto',
                ]
            );

            return ['status' => 'success', 'type' => 'pending', 'amount' => $bonusAmount];
        }
    }

    /**
     * 触发层碰奖
     * 
     * @param Order $order 订单
     * @return array 结果数组
     */
    public function triggerLevelPairBonus(Order $order): array
    {
        return $this->detectLevelPairHits($order);
    }

    /**
     * 层碰点亮与奖励（无限层，比例取 level_pair_rate）
     */
    public function detectLevelPairHits(Order $order): array
    {
        $user = $order->user;
        $pvAmount = $order->quantity * $this->pvUnit;
        $levelPairRate = $this->config['level_pair_rate'] ?? 0.25;
        $chain = $this->getPlacementChain($user);
        $results = [];

        foreach ($chain as $node) {
            $level = $node['level'];
            $position = $node['position'];
            $recipientId = $node['user_id'];

            // 幂等：同一订单已产生层碰流水或待处理则跳过
            $existsTrx = Transaction::where('user_id', $recipientId)
                ->where('remark', 'level_pair_bonus')
                ->where('source_type', 'order')
                ->where('source_id', $order->trx)
                ->exists();
            $existsPending = PendingBonus::where('recipient_id', $recipientId)
                ->where('bonus_type', 'level_pair')
                ->where('source_type', 'order')
                ->where('source_id', $order->trx)
                ->exists();
            if ($existsTrx || $existsPending) {
                continue;
            }

            $levelHit = UserLevelHit::firstOrCreate(
                [
                    'user_id' => $recipientId,
                    'level' => $level,
                    'position' => $position,
                ],
                [
                    'amount' => 0,
                    'first_hit_at' => now(),
                    'order_trx' => $order->trx,
                ]
            );
            $levelHit->amount = ($levelHit->amount ?? 0) + $pvAmount;
            $levelHit->order_trx = $order->trx;
            $levelHit->save();

            // 检查左右是否都点亮且未发放
            $leftHit = UserLevelHit::where('user_id', $recipientId)->where('level', $level)->where('position', 1)->first();
            $rightHit = UserLevelHit::where('user_id', $recipientId)->where('level', $level)->where('position', 2)->first();
            $alreadyRewarded = UserLevelHit::where('user_id', $recipientId)->where('level', $level)->where('rewarded', 1)->exists();

            if ($leftHit && $rightHit && !$alreadyRewarded) {
                $levelPairAmount = $pvAmount * $levelPairRate;
                $recipient = User::find($recipientId);

                if ($recipient && $this->isActivated($recipient)) {
                    $newBalance = $recipient->balance + $levelPairAmount;
                    $recipient->balance = $newBalance;
                    $recipient->save();

                    Transaction::create([
                        'user_id' => $recipientId,
                        'trx_type' => '+',
                        'amount' => $levelPairAmount,
                        'charge' => 0,
                        'post_balance' => $newBalance,
                        'remark' => 'level_pair_bonus',
                        'source_type' => 'order',
                        'source_id' => $order->trx,
                        'details' => json_encode(['order_id' => $order->id, 'level' => $level]),
                    ]);
                } elseif ($recipient) {
                    PendingBonus::updateOrCreate(
                        [
                            'recipient_id' => $recipientId,
                            'bonus_type' => 'level_pair',
                            'source_type' => 'order',
                            'source_id' => $order->trx,
                        ],
                        [
                            'amount' => $levelPairAmount,
                            'accrued_week_key' => $this->getCurrentWeekKey(),
                            'status' => 'pending',
                            'release_mode' => 'auto',
                        ]
                    );
                }

                UserLevelHit::where('user_id', $recipientId)->where('level', $level)->update([
                    'rewarded' => 1,
                    'bonus_amount' => $levelPairAmount,
                ]);

                $results[] = [
                    'user_id' => $recipientId,
                    'level' => $level,
                    'amount' => $levelPairAmount,
                ];
            }
        }

        return [
            'hits_count' => count($results),
            'rewards' => $results,
        ];
    }

    /**
     * 获取安置链（无限层，直到根）
     */
    private function getUplineChain(User $user): array
    {
        return $this->getPlacementChain($user);
    }

    /**
     * 获取安置链（无限层，直到根或无pos_id）
     */
    private function getPlacementChain(User $user): array
    {
        $chain = [];
        $current = $user;
        $level = 0;

        while ($current && $current->pos_id) {
            $parent = User::find($current->pos_id);
            if (!$parent) {
                break;
            }
            $level++;
            $position = $current->position ?: 1;

            $chain[] = [
                'user_id' => $parent->id,
                'user' => $parent,
                'position' => $position,
                'level' => $level,
            ];

            $current = $parent;
        }

        return $chain;
    }

    /**
     * 获取当前周键
     */
    private function getCurrentWeekKey(): string
    {
        return date('o-\WW');
    }

    /**
     * 用户激活时释放待处理奖金
     */
    public function releasePendingBonusesOnActivation(int $userId): array
    {
        $pendingBonuses = PendingBonus::where('recipient_id', $userId)
            ->where('status', 'pending')
            ->where('release_mode', 'auto')
            ->get();

        $released = [];

        foreach ($pendingBonuses as $bonus) {
            DB::transaction(function () use ($bonus, &$released) {
                $user = User::find($bonus->recipient_id);
                $newBalance = $user ? ($user->balance + $bonus->amount) : $bonus->amount;
                if ($user) {
                    $user->balance = $newBalance;
                    $user->save();
                }
                // 写入transactions
                $trx = Transaction::create([
                    'user_id' => $bonus->recipient_id,
                    'trx_type' => '+',
                    'amount' => $bonus->amount,
                    'charge' => 0,
                    'post_balance' => $newBalance,
                    'remark' => 'pending_bonus_release',
                    'source_type' => $bonus->source_type,
                    'source_id' => $bonus->source_id,
                    'details' => json_encode(['pending_bonus_id' => $bonus->id])
                ]);

                // 更新pending_bonuses状态
                $bonus->status = 'released';
                $bonus->released_trx = $trx->id;
                $bonus->save();

                $released[] = [
                    'bonus_type' => $bonus->bonus_type,
                    'amount' => $bonus->amount,
                    'trx_id' => $trx->id
                ];
            });
        }

        return $released;
    }

    /**
     * 兼容调用别名
     */
    public function releasePendingBonuses(int $userId): array
    {
        return $this->releasePendingBonusesOnActivation($userId);
    }
}

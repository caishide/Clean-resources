<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Transaction;
use App\Models\PendingBonus;
use Illuminate\Support\Facades\DB;

class BonusRepository
{
    /**
     * 获取用户直推奖金统计
     */
    public function getUserDirectBonusStats(int $userId, string $startDate, string $endDate): array
    {
        $stats = Transaction::where('user_id', $userId)
            ->where('remark', 'direct_bonus')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('COUNT(*) as count, SUM(amount) as total_amount')
            ->first();
            
        return [
            'count' => $stats->count ?? 0,
            'total_amount' => $stats->total_amount ?? 0
        ];
    }

    /**
     * 获取用户层碰奖金统计
     */
    public function getUserLevelPairBonusStats(int $userId, string $startDate, string $endDate): array
    {
        $stats = Transaction::where('user_id', $userId)
            ->where('remark', 'level_pair_bonus')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('COUNT(*) as count, SUM(amount) as total_amount')
            ->first();
            
        return [
            'count' => $stats->count ?? 0,
            'total_amount' => $stats->total_amount ?? 0
        ];
    }

    /**
     * 获取待处理奖金列表
     */
    public function getPendingBonuses(array $filters = []): array
    {
        $query = PendingBonus::with(['recipient:id,username,email']);
        
        if (!empty($filters['recipient_id'])) {
            $query->where('recipient_id', $filters['recipient_id']);
        }
        
        if (!empty($filters['bonus_type'])) {
            $query->where('bonus_type', $filters['bonus_type']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['release_mode'])) {
            $query->where('release_mode', $filters['release_mode']);
        }
        
        return $query->orderBy('created_at', 'desc')->paginate(20)->toArray();
    }

    /**
     * 批量释放待处理奖金
     */
    public function batchReleasePendingBonuses(array $bonusIds): array
    {
        $results = [];
        
        DB::transaction(function () use ($bonusIds, &$results) {
            foreach ($bonusIds as $bonusId) {
                $bonus = PendingBonus::find($bonusId);
                if (!$bonus || $bonus->status !== 'pending') {
                    $results[$bonusId] = ['status' => 'failed', 'message' => 'Bonus not found or already processed'];
                    continue;
                }
                // 仅允许处理需要人工释放的奖金（若业务需要可调整）
                if ($bonus->release_mode === 'auto') {
                    $results[$bonusId] = ['status' => 'failed', 'message' => 'Auto bonuses are released on activation'];
                    continue;
                }

                $user = $bonus->recipient;
                $newBalance = $user ? ($user->balance + $bonus->amount) : $bonus->amount;
                if ($user) {
                    $user->balance = $newBalance;
                    $user->save();
                }
                
                // 创建交易记录
                $trx = Transaction::create([
                    'user_id' => $bonus->recipient_id,
                    'trx_type' => '+',
                    'amount' => $bonus->amount,
                    'remark' => 'pending_bonus_release',
                    'source_type' => $bonus->source_type,
                    'source_id' => $bonus->source_id,
                    'post_balance' => $newBalance,
                    'charge' => 0,
                    'details' => json_encode(['pending_bonus_id' => $bonus->id])
                ]);
                
                // 更新奖金状态
                $bonus->status = 'released';
                $bonus->released_trx = $trx->id;
                $bonus->save();
                
                $results[$bonusId] = ['status' => 'success', 'trx_id' => $trx->id];
            }
        });
        
        return $results;
    }
}

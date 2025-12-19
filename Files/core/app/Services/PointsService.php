<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPointsLog;
use App\Models\UserExtra;
use App\Models\UserAsset;
use Illuminate\Support\Facades\DB;

class PointsService
{
    /**
     * 莲子积分 - 自购（A类）
     * 
     * @param int $userId 用户ID
     * @param int $quantity 购买数量
     * @param string $orderTrx 订单号
     * @return array 结果
     */
    public function creditSelfPurchase(int $userId, int $quantity, string $orderTrx = ''): array
    {
        $points = $quantity * 3000; // 每台3000莲子
        
        DB::transaction(function () use ($userId, $points, $orderTrx) {
            $log = UserPointsLog::where('user_id', $userId)
                ->where('source_type', 'PURCHASE')
                ->where('source_id', $orderTrx)
                ->lockForUpdate()
                ->first();

            $delta = (float) $points;
            if ($log) {
                $old = (float) ($log->points ?? 0);
                $delta = (float) $points - $old;
                if (abs($delta) < 0.00000001) {
                    return;
                }
                $log->points = $points;
                $log->description = '自购莲子';
                $log->save();
            } else {
                UserPointsLog::create([
                    'user_id' => $userId,
                    'source_type' => 'PURCHASE',
                    'source_id' => $orderTrx,
                    'points' => $points,
                    'description' => '自购莲子',
                ]);
            }

            // 更新用户莲子余额（按差额变更，保证幂等）
            $userExtra = UserExtra::firstOrCreate(['user_id' => $userId]);
            $userExtra->points = ($userExtra->points ?? 0) + $delta;
            $userExtra->save();

            // 同步 user_assets
            $asset = UserAsset::firstOrCreate(['user_id' => $userId]);
            $asset->points = ($asset->points ?? 0) + $delta;
            $asset->save();
        });
        
        return ['status' => 'success', 'points' => $points];
    }

    /**
     * 莲子积分 - 直推（B类）
     * 
     * @param int $userId 用户ID（推荐人）
     * @param int $quantity 购买数量
     * @param string $orderTrx 订单号
     * @return array 结果
     */
    public function creditDirectReferral(int $userId, int $quantity, string $orderTrx = ''): array
    {
        $points = $quantity * 1500; // 每台1500莲子
        
        DB::transaction(function () use ($userId, $points, $orderTrx) {
            $log = UserPointsLog::where('user_id', $userId)
                ->where('source_type', 'DIRECT')
                ->where('source_id', $orderTrx)
                ->lockForUpdate()
                ->first();

            $delta = (float) $points;
            if ($log) {
                $old = (float) ($log->points ?? 0);
                $delta = (float) $points - $old;
                if (abs($delta) < 0.00000001) {
                    return;
                }
                $log->points = $points;
                $log->description = '直推莲子';
                $log->save();
            } else {
                UserPointsLog::create([
                    'user_id' => $userId,
                    'source_type' => 'DIRECT',
                    'source_id' => $orderTrx,
                    'points' => $points,
                    'description' => '直推莲子',
                ]);
            }

            $userExtra = UserExtra::firstOrCreate(['user_id' => $userId]);
            $userExtra->points = ($userExtra->points ?? 0) + $delta;
            $userExtra->save();

            $asset = UserAsset::firstOrCreate(['user_id' => $userId]);
            $asset->points = ($asset->points ?? 0) + $delta;
            $asset->save();
        });
        
        return ['status' => 'success', 'points' => $points];
    }

    /**
     * 莲子积分 - TEAM（C类）
     * 
     * @param int $userId 用户ID
     * @param float $weakPVNew 新增弱区PV
     * @param string $weekKey 周键
     * @return array 结果
     */
    public function creditTeamPoints(int $userId, float $weakPVNew, string $weekKey): array
    {
        $points = $weakPVNew * 0.1; // 弱区新增PV × 0.1
        
        DB::transaction(function () use ($userId, $points, $weekKey) {
            $log = UserPointsLog::where('user_id', $userId)
                ->where('source_type', 'TEAM')
                ->where('source_id', $weekKey)
                ->lockForUpdate()
                ->first();

            $delta = (float) $points;
            if ($log) {
                $old = (float) ($log->points ?? 0);
                $delta = (float) $points - $old;
                if (abs($delta) < 0.00000001) {
                    return;
                }
                $log->points = $points;
                $log->description = "TEAM莲子（{$weekKey}）";
                $log->save();
            } else {
                UserPointsLog::create([
                    'user_id' => $userId,
                    'source_type' => 'TEAM',
                    'source_id' => $weekKey,
                    'points' => $points,
                    'description' => "TEAM莲子（{$weekKey}）",
                ]);
            }

            // 更新用户莲子余额（按差额变更，保证幂等）
            $userExtra = UserExtra::firstOrCreate(['user_id' => $userId]);
            $userExtra->points = ($userExtra->points ?? 0) + $delta;
            $userExtra->save();

            $asset = UserAsset::firstOrCreate(['user_id' => $userId]);
            $asset->points = ($asset->points ?? 0) + $delta;
            $asset->save();
        });
        
        return ['status' => 'success', 'points' => $points];
    }

    /**
     * 莲子积分 - 每日签到（D类）
     * 
     * @param int $userId 用户ID
     * @return array 结果
     */
    public function creditDailyCheckIn(int $userId): array
    {
        // 检查今天是否已经签到
        $todayKey = now()->toDateString();
        $today = UserPointsLog::where('user_id', $userId)
            ->where('source_type', 'DAILY')
            ->where('source_id', $todayKey)
            ->first();
            
        if ($today) {
            return ['status' => 'error', 'message' => '今天已经签到过了'];
        }
        
        $points = 10; // 每天10莲子
        
        DB::transaction(function () use ($userId, $points, $todayKey) {
            UserPointsLog::updateOrCreate(
                [
                    'user_id' => $userId,
                    'source_type' => 'DAILY',
                    'source_id' => $todayKey,
                ],
                [
                    'points' => $points,
                    'description' => '每日签到',
                ]
            );
            
            $userExtra = UserExtra::firstOrCreate(['user_id' => $userId]);
            $userExtra->points = ($userExtra->points ?? 0) + $points;
            $userExtra->save();

            $asset = UserAsset::firstOrCreate(['user_id' => $userId]);
            $asset->points = ($asset->points ?? 0) + $points;
            $asset->save();
        });
        
        return ['status' => 'success', 'points' => $points];
    }

    /**
     * 获取用户莲子余额
     */
    public function getUserPointsBalance(int $userId): float
    {
        $asset = UserAsset::where('user_id', $userId)->first();
        if ($asset) {
            return $asset->points ?? 0;
        }
        $userExtra = UserExtra::where('user_id', $userId)->first();
        return $userExtra->points ?? 0;
    }

    /**
     * 获取用户莲子积分历史
     */
    public function getUserPointsHistory(int $userId, int $limit = 50): array
    {
        $logs = UserPointsLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
            
        return $logs;
    }

    /**
     * 用户端积分概览
     */
    public function getUserPointsSummary(int $userId): array
    {
        $total = $this->getUserPointsBalance($userId);
        $byType = $this->getUserPointsByType($userId);

        return [
            'total' => $total,
            'a_class' => $byType['PURCHASE']['total_points'] ?? 0,
            'b_class' => $byType['DIRECT']['total_points'] ?? 0,
            'c_class' => $byType['TEAM']['total_points'] ?? 0,
            'd_class' => $byType['DAILY']['total_points'] ?? 0,
        ];
    }

    /**
     * 按类型统计用户莲子积分
     */
    public function getUserPointsByType(int $userId): array
    {
        $stats = UserPointsLog::where('user_id', $userId)
            ->selectRaw('source_type, SUM(points) as total_points')
            ->groupBy('source_type')
            ->get()
            ->keyBy('source_type')
            ->toArray();
            
        return $stats;
    }
}

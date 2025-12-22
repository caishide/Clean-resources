<?php

namespace App\Services;

use App\Models\User;
use App\Models\BvLog;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Transaction;
use App\Constants\Status;
use App\Helpers\CacheHelper;
use Illuminate\Support\Facades\Cache;

class UserService extends BaseService
{
    /**
     * Get user dashboard statistics
     */
    public function getDashboardStats(int $userId): array
    {
        $cacheKey = "bc20_user:dashboard:{$userId}";

        return Cache::remember($cacheKey, 300, function () use ($userId) {
            $this->logInfo('Fetching dashboard stats', ['user_id' => $userId]);

            return [
                'totalDeposit' => Deposit::where('user_id', $userId)
                    ->where('status', Status::PAYMENT_SUCCESS)
                    ->sum('amount'),

                'totalWithdraw' => Withdrawal::where('user_id', $userId)
                    ->where('status', Status::PAYMENT_SUCCESS)
                    ->sum('amount'),

                'completeWithdraw' => Withdrawal::where('user_id', $userId)
                    ->where('status', Status::PAYMENT_SUCCESS)
                    ->count(),

                'pendingWithdraw' => Withdrawal::where('user_id', $userId)
                    ->where('status', Status::PAYMENT_PENDING)
                    ->count(),

                'totalRef' => User::where('ref_by', $userId)->count(),

                'totalBvCut' => BvLog::where('user_id', $userId)
                    ->where('trx_type', '-')
                    ->sum('amount'),
            ];
        });
    }

    /**
     * Clear user dashboard cache
     */
    public function clearDashboardCache(int $userId): void
    {
        Cache::forget("bc20_user:dashboard:{$userId}");
        CacheHelper::forgetUserProfile($userId);
        CacheHelper::forgetUserBalance($userId);
    }

    /**
     * Get user profile with caching
     */
    public function getProfile(int $userId): ?User
    {
        return CacheHelper::rememberUserProfile($userId, function () use ($userId) {
            return User::with(['userExtra'])->find($userId);
        });
    }

    /**
     * Update user profile
     */
    public function updateProfile(User $user, array $data): bool
    {
        $this->logInfo('Updating user profile', ['user_id' => $user->id]);

        $result = $this->transaction(function () use ($user, $data) {
            $user->fill($data);
            return $user->save();
        });

        if ($result) {
            $this->clearDashboardCache($user->id);
        }

        return $result;
    }

    /**
     * Enable 2FA for user
     */
    public function enable2FA(User $user, string $secret): bool
    {
        $this->logInfo('Enabling 2FA', ['user_id' => $user->id]);

        $user->tsc = $secret;
        $user->ts = Status::ENABLE;
        return $user->save();
    }

    /**
     * Disable 2FA for user
     */
    public function disable2FA(User $user): bool
    {
        $this->logInfo('Disabling 2FA', ['user_id' => $user->id]);

        $user->tsc = null;
        $user->ts = Status::DISABLE;
        return $user->save();
    }

    /**
     * Get user transactions with pagination
     */
    public function getTransactions(int $userId, array $filters = [], int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Transaction::where('user_id', $userId)
            ->searchable(['trx'])
            ->orderBy('id', 'desc');

        if (!empty($filters['trx_type'])) {
            $query->where('trx_type', $filters['trx_type']);
        }

        if (!empty($filters['remark'])) {
            $query->where('remark', $filters['remark']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get distinct transaction remarks for user
     */
    public function getTransactionRemarks(int $userId): \Illuminate\Support\Collection
    {
        return Transaction::where('user_id', $userId)
            ->distinct('remark')
            ->orderBy('remark')
            ->whereNotNull('remark')
            ->pluck('remark');
    }
}

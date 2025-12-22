<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\GeneralSetting;

class CacheHelper
{
    /**
     * 缓存键前缀
     */
    const CACHE_PREFIX = 'bc20_';

    /**
     * 缓存TTL（秒）
     */
    const DEFAULT_TTL = 3600; // 1小时

    /**
     * 用户资料缓存
     */
    public static function rememberUserProfile(int $userId, callable $callback, int $ttl = self::DEFAULT_TTL): array
    {
        $key = self::CACHE_PREFIX . "user:profile:{$userId}";

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * 清除用户资料缓存
     */
    public static function forgetUserProfile(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . "user:profile:{$userId}");
    }

    /**
     * 用户余额缓存
     */
    public static function rememberUserBalance(int $userId, callable $callback, int $ttl = 1800): float
    {
        $key = self::CACHE_PREFIX . "user:balance:{$userId}";

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * 清除用户余额缓存
     */
    public static function forgetUserBalance(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . "user:balance:{$userId}");
    }

    /**
     * 通用设置缓存
     */
    public static function rememberGeneralSettings(callable $callback, int $ttl = self::DEFAULT_TTL * 24): array
    {
        $key = self::CACHE_PREFIX . "general:settings";

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * 清除通用设置缓存
     */
    public static function forgetGeneralSettings(): void
    {
        Cache::forget(self::CACHE_PREFIX . "general:settings");
    }

    /**
     * 语言包缓存
     */
    public static function rememberLanguages(callable $callback, int $ttl = self::DEFAULT_TTL * 24): array
    {
        $key = self::CACHE_PREFIX . "app:languages";

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * 支付网关缓存
     */
    public static function rememberGateways(callable $callback, int $ttl = self::DEFAULT_TTL): array
    {
        $key = self::CACHE_PREFIX . "app:gateways";

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * 清除支付网关缓存
     */
    public static function forgetGateways(): void
    {
        Cache::forget(self::CACHE_PREFIX . "app:gateways");
    }

    /**
     * 用户列表缓存（带分页）
     */
    public static function rememberUsersList(array $filters = [], int $perPage = 20, int $ttl = 900): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $cacheKey = self::CACHE_PREFIX . "users:list:" . md5(serialize($filters)) . ":page:" . request()->get('page', 1);

        return Cache::remember($cacheKey, $ttl, function () use ($filters, $perPage) {
            $query = User::with(['userExtras']);

            // 应用过滤条件
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('username', 'like', "%{$filters['search']}%")
                      ->orWhere('email', 'like', "%{$filters['search']}%");
                });
            }

            return $query->paginate($perPage);
        });
    }

    /**
     * 清除用户列表缓存
     */
    public static function forgetUsersList(): void
    {
        $keys = Cache::getRedis()->keys(self::CACHE_PREFIX . "users:list:*");
        if (!empty($keys)) {
            Cache::getRedis()->del($keys);
        }
    }

    /**
     * 交易统计缓存
     */
    public static function rememberTransactionStats(int $userId, callable $callback, int $ttl = 1800): array
    {
        $key = self::CACHE_PREFIX . "user:transaction:stats:{$userId}";

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * 清除交易统计缓存
     */
    public static function forgetTransactionStats(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . "user:transaction:stats:{$userId}");
    }

    /**
     * 清除用户相关所有缓存
     */
    public static function forgetUserAllCache(int $userId): void
    {
        self::forgetUserProfile($userId);
        self::forgetUserBalance($userId);
        self::forgetTransactionStats($userId);

        // 清除用户列表缓存
        self::forgetUsersList();
    }

    /**
     * 清除所有应用缓存
     */
    public static function forgetAllAppCache(): void
    {
        self::forgetGeneralSettings();
        self::forgetGateways();
        self::forgetUsersList();

        $keys = Cache::getRedis()->keys(self::CACHE_PREFIX . "*");
        if (!empty($keys)) {
            Cache::getRedis()->del($keys);
        }
    }

    /**
     * 缓存统计信息
     */
    public static function getCacheStats(): array
    {
        $redis = Cache::getRedis();
        $keys = $redis->keys(self::CACHE_PREFIX . "*");

        return [
            'total_keys' => count($keys),
            'memory_usage' => $redis->info('memory')['used_memory_human'] ?? 'N/A',
            'connected_clients' => $redis->info('clients')['connected_clients'] ?? 'N/A',
        ];
    }

    /**
     * 预热常用缓存
     */
    public static function warmupCache(): void
    {
        // 预热通用设置
        self::rememberGeneralSettings(function () {
            return GeneralSetting::pluck('data', 'key')->toArray();
        });

        // 预热语言包
        self::rememberLanguages(function () {
            return DB::table('languages')->where('status', 1)->pluck('name', 'code')->toArray();
        });

        // 预热支付网关
        self::rememberGateways(function () {
            return DB::table('gateways')->where('status', 1)->get()->toArray();
        });
    }
}

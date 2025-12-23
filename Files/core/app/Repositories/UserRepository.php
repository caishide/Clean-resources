<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\UserExtra;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * 用户仓储类 - 优化查询性能
 *
 * 避免SELECT *，使用索引优化查询，实施Keyset分页和缓存策略
 */
class UserRepository
{
    /**
     * 获取用户列表（优化版）
     *
     * @param array $filters 过滤条件
     * @param int $perPage 每页数量
     * @param int|null $lastId 上次查询的最后ID（Keyset分页）
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|Collection
     */
    public function getUserList(array $filters = [], int $perPage = 20, ?int $lastId = null): LengthAwarePaginator|Collection
    {
        // 构建缓存键
        $cacheKey = $this->buildCacheKey('user_list', $filters, $perPage, $lastId);

        return Cache::remember($cacheKey, 300, function () use ($filters, $perPage, $lastId) {
            $query = User::query();

            if (!empty($filters['scope']) && is_callable([User::class, $filters['scope']])) {
                $query = User::{$filters['scope']}();
            }

            $query = $query->select([ // ✅ 避免SELECT *，只选择需要的字段
                'id',
                'username',
                'email',
                'status',
                'ev',
                'sv',
                'balance',
                'plan_id',
                'ref_by',
                'pos_id',
                'position',
                'created_at',
                'updated_at'
            ])
            ->with(['userExtra:id,user_id,bv_left,bv_right,points']) // ✅ 只选择需要的关联字段
            ->when($filters['status'] ?? null, function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('username', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                });
            });

            // ✅ Keyset分页（替代OFFSET分页）
            if ($lastId) {
                $query->where('id', '<', $lastId);
                return $query->orderBy('id', 'desc')
                    ->limit($perPage)
                    ->get();
            }

            return $query->orderBy('id', 'desc')
                ->paginate($perPage);
        });
    }

    /**
     * 获取用户详情（优化版）
     *
     * @param int $userId 用户ID
     * @return User|null
     */
    public function getUserDetail(int $userId): ?User
    {
        $cacheKey = "user_detail:{$userId}";

        return Cache::remember($cacheKey, 600, function () use ($userId) {
            return User::query()
                ->select([ // ✅ 只选择需要的字段
                    'id',
                    'username',
                    'email',
                    'firstname',
                    'lastname',
                    'status',
                    'ev',
                    'sv',
                    'balance',
                    'total_invest',
                    'plan_id',
                    'ref_by',
                    'pos_id',
                    'position',
                    'created_at'
                ])
                ->with([
                    'userExtra:id,user_id,bv_left,bv_right,points,paid_left,paid_right,free_left,free_right',
                    'transactions' => function ($q) {
                        $q->select([ // ✅ 只选择需要的字段
                            'id',
                            'user_id',
                            'trx',
                            'trx_type',
                            'amount',
                            'post_balance',
                            'remark',
                            'created_at'
                        ])
                        ->latest()
                        ->limit(50);
                    },
                    'deposits' => function ($q) {
                        $q->select([ // ✅ 只选择需要的字段
                            'id',
                            'user_id',
                            'method_code',
                            'amount',
                            'status',
                            'created_at'
                        ])
                        ->latest()
                        ->limit(20);
                    },
                    'withdrawals' => function ($q) {
                        $q->select([ // ✅ 只选择需要的字段
                            'id',
                            'user_id',
                            'method_code',
                            'amount',
                            'status',
                            'created_at'
                        ])
                        ->latest()
                        ->limit(20);
                    },
                    'orders' => function ($q) {
                        $q->select([ // ✅ 只选择需要的字段
                            'id',
                            'user_id',
                            'product_id',
                            'quantity',
                            'total_price',
                            'status',
                            'created_at'
                        ])
                        ->with('product:id,name,price') // ✅ 只选择需要的字段
                        ->latest()
                        ->limit(20);
                    }
                ])
                ->find($userId);
        });
    }

    /**
     * 获取用户交易历史（优化版）
     *
     * @param int $userId 用户ID
     * @param array $filters 过滤条件
     * @param int $perPage 每页数量
     * @param int|null $lastId 上次查询的最后ID（Keyset分页）
     * @return Collection
     */
    public function getUserTransactions(int $userId, array $filters = [], int $perPage = 20, ?int $lastId = null): Collection
    {
        $cacheKey = $this->buildCacheKey("user_{$userId}_transactions", $filters, $perPage, $lastId);

        return Cache::remember($cacheKey, 180, function () use ($userId, $filters, $perPage, $lastId) {
            $query = \App\Models\Transaction::query()
                ->select([ // ✅ 只选择需要的字段
                    'id',
                    'user_id',
                    'trx',
                    'trx_type',
                    'amount',
                    'charge',
                    'post_balance',
                    'remark',
                    'created_at'
                ])
                ->where('user_id', $userId)
                ->when($filters['trx_type'] ?? null, function ($q, $type) {
                    $q->where('trx_type', $type);
                })
                ->when($filters['remark'] ?? null, function ($q, $remark) {
                    $q->where('remark', 'like', "%{$remark}%");
                })
                ->when($filters['date_from'] ?? null, function ($q, $date) {
                    $q->whereDate('created_at', '>=', $date);
                })
                ->when($filters['date_to'] ?? null, function ($q, $date) {
                    $q->whereDate('created_at', '<=', $date);
                });

            // ✅ Keyset分页
            if ($lastId) {
                $query->where('id', '<', $lastId);
            }

            return $query->orderBy('id', 'desc')
                ->limit($perPage)
                ->get();
        });
    }

    /**
     * 获取订单列表（优化版）
     *
     * @param array $filters 过滤条件
     * @param int $perPage 每页数量
     * @param int|null $lastId 上次查询的最后ID（Keyset分页）
     * @return Collection
     */
    public function getOrderList(array $filters = [], int $perPage = 20, ?int $lastId = null): Collection
    {
        $cacheKey = $this->buildCacheKey('order_list', $filters, $perPage, $lastId);

        return Cache::remember($cacheKey, 300, function () use ($filters, $perPage, $lastId) {
            $query = \App\Models\Order::query()
                ->select([ // ✅ 只选择需要的字段
                    'id',
                    'user_id',
                    'product_id',
                    'quantity',
                    'price',
                    'total_price',
                    'status',
                    'trx',
                    'created_at'
                ])
                ->with([
                    'user:id,username,email',
                    'product:id,name,price,thumbnail'
                ])
                ->when($filters['status'] ?? null, function ($q, $status) {
                    $q->where('status', $status);
                })
                ->when($filters['user_id'] ?? null, function ($q, $userId) {
                    $q->where('user_id', $userId);
                })
                ->when($filters['search'] ?? null, function ($q, $search) {
                    $q->where(function ($query) use ($search) {
                        $query->where('trx', 'like', "%{$search}%")
                              ->orWhereHas('user', function ($userQuery) use ($search) {
                                  $userQuery->where('username', 'like', "%{$search}%");
                              })
                              ->orWhereHas('product', function ($productQuery) use ($search) {
                                  $productQuery->where('name', 'like', "%{$search}%");
                              });
                    });
                });

            // ✅ Keyset分页
            if ($lastId) {
                $query->where('id', '<', $lastId);
            }

            return $query->orderBy('id', 'desc')
                ->limit($perPage)
                ->get();
        });
    }

    /**
     * 获取存款列表（优化版）
     *
     * @param array $filters 过滤条件
     * @param int $perPage 每页数量
     * @param int|null $lastId 上次查询的最后ID（Keyset分页）
     * @return Collection
     */
    public function getDepositList(array $filters = [], int $perPage = 20, ?int $lastId = null): Collection
    {
        $cacheKey = $this->buildCacheKey('deposit_list', $filters, $perPage, $lastId);

        return Cache::remember($cacheKey, 300, function () use ($filters, $perPage, $lastId) {
            $query = \App\Models\Deposit::query()
                ->select([ // ✅ 只选择需要的字段
                    'id',
                    'user_id',
                    'method_code',
                    'amount',
                    'charge',
                    'final_amount',
                    'status',
                    'trx',
                    'created_at'
                ])
                ->with('user:id,username,email')
                ->when($filters['status'] ?? null, function ($q, $status) {
                    $q->where('status', $status);
                })
                ->when($filters['user_id'] ?? null, function ($q, $userId) {
                    $q->where('user_id', $userId);
                })
                ->when($filters['search'] ?? null, function ($q, $search) {
                    $q->where(function ($query) use ($search) {
                        $query->where('trx', 'like', "%{$search}%")
                              ->orWhereHas('user', function ($userQuery) use ($search) {
                                  $userQuery->where('username', 'like', "%{$search}%");
                              });
                    });
                });

            // ✅ Keyset分页
            if ($lastId) {
                $query->where('id', '<', $lastId);
            }

            return $query->orderBy('id', 'desc')
                ->limit($perPage)
                ->get();
        });
    }

    /**
     * 获取提现列表（优化版）
     *
     * @param array $filters 过滤条件
     * @param int $perPage 每页数量
     * @param int|null $lastId 上次查询的最后ID（Keyset分页）
     * @return Collection
     */
    public function getWithdrawalList(array $filters = [], int $perPage = 20, ?int $lastId = null): Collection
    {
        $cacheKey = $this->buildCacheKey('withdrawal_list', $filters, $perPage, $lastId);

        return Cache::remember($cacheKey, 300, function () use ($filters, $perPage, $lastId) {
            $query = \App\Models\Withdrawal::query()
                ->select([ // ✅ 只选择需要的字段
                    'id',
                    'user_id',
                    'method_code',
                    'amount',
                    'charge',
                    'final_amount',
                    'status',
                    'trx',
                    'created_at'
                ])
                ->with('user:id,username,email')
                ->when($filters['status'] ?? null, function ($q, $status) {
                    $q->where('status', $status);
                })
                ->when($filters['user_id'] ?? null, function ($q, $userId) {
                    $q->where('user_id', $userId);
                })
                ->when($filters['search'] ?? null, function ($q, $search) {
                    $q->where(function ($query) use ($search) {
                        $query->where('trx', 'like', "%{$search}%")
                              ->orWhereHas('user', function ($userQuery) use ($search) {
                                  $userQuery->where('username', 'like', "%{$search}%");
                              });
                    });
                });

            // ✅ Keyset分页
            if ($lastId) {
                $query->where('id', '<', $lastId);
            }

            return $query->orderBy('id', 'desc')
                ->limit($perPage)
                ->get();
        });
    }

    /**
     * 清理用户相关缓存
     *
     * @param int $userId
     */
    public function clearUserCache(int $userId): void
    {
        Cache::forget("user_detail:{$userId}");
        Cache::forget("user_transactions:{$userId}");
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['users'])->flush();
        }
    }

    /**
     * 构建缓存键
     *
     * @param string $prefix
     * @param array $params
     * @param int $perPage
     * @param int|null $lastId
     * @return string
     */
    private function buildCacheKey(string $prefix, array $params, int $perPage, ?int $lastId): string
    {
        $key = $prefix . ':' . md5(serialize($params)) . ":{$perPage}";
        if ($lastId) {
            $key .= ":{$lastId}";
        }
        return $key;
    }
}

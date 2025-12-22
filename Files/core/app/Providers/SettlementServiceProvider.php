<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * V10.1结算系统服务提供者
 */
class SettlementServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 绑定Service单例
        $this->app->singleton(\App\Services\BonusService::class);
        $this->app->singleton(\App\Services\SettlementService::class);
        $this->app->singleton(\App\Services\PVLedgerService::class);
        $this->app->singleton(\App\Services\PointsService::class);
        $this->app->singleton(\App\Services\AdjustmentService::class);

        // 绑定Repository
        $this->app->singleton(\App\Repositories\BonusRepository::class);
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 注册订单观察者
        Order::observe(OrderObserver::class);

        // 加载结算API路由 (注意：需要应用api前缀和中间件)
        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api_settlement.php'));

        // 注册Artisan命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\WeeklySettlementCommand::class,
                \App\Console\Commands\QuarterlySettlementCommand::class,
                \App\Console\Commands\FinalizeAdjustmentBatchCommand::class,
                \App\Console\Commands\SimulateWeeklyBonuses::class,
            ]);
        }
    }
}

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SettlementController;

/*
|--------------------------------------------------------------------------
| V10.1 结算系统API路由
|--------------------------------------------------------------------------
*/

Route::prefix('api')->middleware(['auth:sanctum'])->group(function () {

    // 用户端接口
    Route::prefix('user')->group(function () {
        // PV查询
        Route::get('pv-summary', [SettlementController::class, 'getUserPVSummary']);

        // 积分查询
        Route::get('points-summary', [SettlementController::class, 'getUserPointsSummary']);

        // 奖金历史
        Route::get('bonus-history', [SettlementController::class, 'getUserBonusHistory']);

        // 待处理奖金
        Route::get('pending-bonuses', [SettlementController::class, 'getPendingBonuses']);
    });

    // 管理员端接口
    Route::prefix('admin')->middleware(['admin'])->group(function () {
        // 结算管理
        Route::get('settlements', [SettlementController::class, 'getSettlements']);
        Route::post('settlements/dry-run', [SettlementController::class, 'dryRunSettlement']);
        Route::post('settlements/execute', [SettlementController::class, 'executeSettlement']);
        Route::get('settlements/{week}/k-factor', [SettlementController::class, 'getKFactorDetails']);

        // 奖金管理
        Route::post('bonuses/release', [SettlementController::class, 'releasePendingBonuses']);
    });
});

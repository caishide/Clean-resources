<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes (加载自 SettlementServiceProvider)
|--------------------------------------------------------------------------
*/

// 健康检查 (公开)
Route::get('health', [HealthController::class, 'check']);
Route::get('health/detailed', [HealthController::class, 'detailed']);
Route::get('ping', [HealthController::class, 'ping']);

// 认证接口 (公开登录，其他需认证)
Route::post('auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
});

// 用户端接口 (需要认证)
Route::middleware(['auth:sanctum'])->prefix('user')->group(function () {
    // PV查询
    Route::get('pv-summary', [SettlementController::class, 'getUserPVSummary']);

    // 积分查询
    Route::get('points-summary', [SettlementController::class, 'getUserPointsSummary']);

    // 奖金历史
    Route::get('bonus-history', [SettlementController::class, 'getUserBonusHistory']);

    // 待处理奖金
    Route::get('pending-bonuses', [SettlementController::class, 'getPendingBonuses']);
});

// 管理员端接口 (需要认证+管理员权限)
Route::middleware(['auth:sanctum', 'api.admin'])->prefix('admin')->group(function () {
    // 结算管理
    Route::get('settlements', [SettlementController::class, 'getSettlements']);
    Route::post('settlements/dry-run', [SettlementController::class, 'dryRunSettlement']);
    Route::post('settlements/execute', [SettlementController::class, 'executeSettlement']);
    Route::get('settlements/{week}/k-factor', [SettlementController::class, 'getKFactorDetails']);

    // 奖金管理
    Route::post('bonuses/release', [SettlementController::class, 'releasePendingBonuses']);
});

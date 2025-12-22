<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check endpoints (公开，无需认证)
Route::get('health', [HealthController::class, 'check']);
Route::get('health/detailed', [HealthController::class, 'detailed']);
Route::get('ping', [HealthController::class, 'ping']);

// Auth endpoints
Route::post('auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
});

// Settlement API routes 由 SettlementServiceProvider 加载
// 路由定义在 routes/api_settlement.php

// Default API route
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Health check endpoints
Route::get('/health', [HealthController::class, 'check']);
Route::get('/health/detailed', [HealthController::class, 'detailed']);
Route::get('/ping', [HealthController::class, 'ping']);

// Settlement API routes (existing)
Route::prefix('settlement')->group(base_path('routes/api_settlement.php'));

// Default API route
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

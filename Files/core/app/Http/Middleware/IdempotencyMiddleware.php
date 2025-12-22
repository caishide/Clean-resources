<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    private const CACHE_TTL = 86400; // 24小时

    /**
     * 幂等性中间件
     * 通过 X-Idempotency-Key 头实现请求幂等
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 仅处理写操作
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key');

        // 无幂等Key则正常处理
        if (!$idempotencyKey) {
            return $next($request);
        }

        // 验证Key格式 (UUID或自定义格式)
        if (strlen($idempotencyKey) > 128) {
            return response()->json([
                'error' => 'Idempotency key too long (max 128 chars)'
            ], 400);
        }

        // 构建缓存键 (用户隔离)
        $userId = $request->user()?->id ?? 'guest';
        $cacheKey = "idempotency:{$userId}:{$idempotencyKey}";

        // 检查是否已处理
        if ($cached = Cache::get($cacheKey)) {
            return response()->json(
                json_decode($cached['body'], true),
                $cached['status'],
                ['X-Idempotency-Replayed' => 'true']
            );
        }

        // 处理请求
        $response = $next($request);

        // 缓存成功响应 (2xx)
        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(),
            ], self::CACHE_TTL);
        }

        return $response;
    }
}

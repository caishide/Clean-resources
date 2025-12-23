<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestTracingMiddleware
{
    /**
     * 请求追踪中间件 - 轻量版
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        // 仅生成trace_id，不记录日志
        $traceId = $request->header('X-Trace-ID') ?? Str::ulid()->toString();
        $request->attributes->set('trace_id', $traceId);
        $request->attributes->set('start_time', $startTime);

        $response = $next($request);

        // 计算响应时间
        $duration = (microtime(true) - $startTime) * 1000;

        // 添加追踪头
        $response->headers->set('X-Trace-ID', $traceId);
        $response->headers->set('X-Response-Time', round($duration, 2) . 'ms');

        return $response;
    }
}

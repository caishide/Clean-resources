<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMiddleware
{
    /**
     * 轻量级性能中间件 - 仅在debug模式下记录查询
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 在debug模式下启用查询日志
        if (config('app.debug') && class_exists(\App\Services\PerformanceMonitor::class)) {
            \App\Services\PerformanceMonitor::enableQueryLogging();
        }

        return $next($request);
    }
}

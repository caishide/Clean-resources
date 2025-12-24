<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;

/**
 * 权限检查中间件
 * 
 * 使用方法：
 * Route::middleware(['permission:view users'])->group(...);
 * 或
 * Route::middleware(['permission:view users|edit users'])->group(...);
 */
class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string|null  ...$permissions
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        // 检查用户是否登录
        if (!Auth::check()) {
            return redirect()->route('login')
                ->with('error', '请先登录');
        }

        $user = Auth::user();

        // 超级管理员跳过权限检查
        if ($user->hasRole('Super Admin')) {
            return $next($request);
        }

        // 检查权限
        foreach ($permissions as $permission) {
            // 支持多个权限用 | 分隔（满足任意一个即可）
            if (str_contains($permission, '|')) {
                $anyPermissions = explode('|', $permission);
                $hasAnyPermission = false;
                
                foreach ($anyPermissions as $perm) {
                    if ($user->can(trim($perm))) {
                        $hasAnyPermission = true;
                        break;
                    }
                }
                
                if (!$hasAnyPermission) {
                    throw UnauthorizedException::forPermissions($anyPermissions);
                }
            } else {
                // 单个权限检查
                if (!$user->can($permission)) {
                    throw UnauthorizedException::forPermissions([$permission]);
                }
            }
        }

        return $next($request);
    }
}
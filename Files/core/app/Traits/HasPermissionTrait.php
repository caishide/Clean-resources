<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;

/**
 * 权限检查 Trait
 * 
 * 在控制器中使用此 Trait 简化权限检查
 * 
 * 使用方法：
 * 1. 在控制器中引入：use HasPermissionTrait;
 * 2. 在方法中调用：$this->checkPermission('view users');
 */
trait HasPermissionTrait
{
    /**
     * 检查用户是否有指定权限
     *
     * @param string|array $permissions 权限名称或权限数组
     * @param bool $requireAll 是否需要拥有所有权限（默认 false，满足任意一个即可）
     * @return bool
     * @throws UnauthorizedException
     */
    protected function checkPermission(string|array $permissions, bool $requireAll = false): bool
    {
        if (!Auth::check()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $user = Auth::user();

        // 超级管理员跳过权限检查
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if (is_array($permissions)) {
            if ($requireAll) {
                // 需要拥有所有权限
                foreach ($permissions as $permission) {
                    if (!$user->can($permission)) {
                        throw UnauthorizedException::forPermissions($permissions);
                    }
                }
                return true;
            } else {
                // 满足任意一个权限即可
                foreach ($permissions as $permission) {
                    if ($user->can($permission)) {
                        return true;
                    }
                }
                throw UnauthorizedException::forPermissions($permissions);
            }
        }

        // 单个权限检查
        if (!$user->can($permissions)) {
            throw UnauthorizedException::forPermissions([$permissions]);
        }

        return true;
    }

    /**
     * 检查用户是否有指定角色
     *
     * @param string|array $roles 角色名称或角色数组
     * @param bool $requireAll 是否需要拥有所有角色（默认 false）
     * @return bool
     * @throws UnauthorizedException
     */
    protected function checkRole(string|array $roles, bool $requireAll = false): bool
    {
        if (!Auth::check()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $user = Auth::user();

        // 超级管理员跳过角色检查
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if (is_array($roles)) {
            if ($requireAll) {
                foreach ($roles as $role) {
                    if (!$user->hasRole($role)) {
                        throw new UnauthorizedException(403, '用户没有所需的角色', $roles);
                    }
                }
                return true;
            } else {
                foreach ($roles as $role) {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                }
                throw new UnauthorizedException(403, '用户没有所需的角色', $roles);
            }
        }

        if (!$user->hasRole($roles)) {
            throw new UnauthorizedException(403, '用户没有所需的角色', [$roles]);
        }

        return true;
    }

    /**
     * 检查是否为管理员
     *
     * @return bool
     */
    protected function isAdmin(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return Auth::user()->hasRole(['Super Admin', 'Admin']);
    }

    /**
     * 检查是否为超级管理员
     *
     * @return bool
     */
    protected function isSuperAdmin(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return Auth::user()->hasRole('Super Admin');
    }

    /**
     * 获取当前登录用户
     *
     * @return \App\Models\User|null
     */
    protected function getCurrentUser()
    {
        return Auth::user();
    }

    /**
     * 获取当前用户ID
     *
     * @return int|null
     */
    protected function getCurrentUserId(): ?int
    {
        return Auth::id();
    }
}
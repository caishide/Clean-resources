<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Traits\HasPermissionTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 权限系统单元测试
 */
class PermissionSystemTest extends TestCase
{
    
    /**
     * 测试角色创建
     */
    public function test_role_creation(): void
    {
        $role = Role::create(['name' => 'test_role']);

        $this->assertDatabaseHas('roles', [
            'name' => 'test_role',
        ]);

        $this->assertEquals('test_role', $role->name);
    }

    /**
     * 测试权限创建
     */
    public function test_permission_creation(): void
    {
        $permission = Permission::create(['name' => 'test.permission']);

        $this->assertDatabaseHas('permissions', [
            'name' => 'test.permission',
        ]);

        $this->assertEquals('test.permission', $permission->name);
    }

    /**
     * 测试为角色分配权限
     */
    public function test_assign_permission_to_role(): void
    {
        $role = Role::create(['name' => 'admin']);
        $permission = Permission::create(['name' => 'edit.users']);

        $role->givePermissionTo($permission);

        $this->assertTrue($role->hasPermissionTo('edit.users'));
    }

    /**
     * 测试为用户分配角色
     */
    public function test_assign_role_to_user(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin']);

        $user->assignRole($role);

        $this->assertTrue($user->hasRole('admin'));
    }

    /**
     * 测试为用户直接分配权限
     */
    public function test_assign_permission_to_user(): void
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'edit.users']);

        $user->givePermissionTo($permission);

        $this->assertTrue($user->hasPermissionTo('edit.users'));
    }

    /**
     * 测试用户通过角色获得权限
     */
    public function test_user_permission_via_role(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin']);
        $permission = Permission::create(['name' => 'edit.users']);

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->hasPermissionTo('edit.users'));
    }

    /**
     * 测试超级管理员拥有所有权限
     */
    public function test_super_admin_has_all_permissions(): void
    {
        $user = User::factory()->create();
        $superAdminRole = Role::create(['name' => 'Super Admin']);

        $user->assignRole($superAdminRole);

        // 创建一些测试权限
        Permission::create(['name' => 'edit.users']);
        Permission::create(['name' => 'delete.users']);
        Permission::create(['name' => 'create.settlements']);

        // 超级管理员应该拥有所有权限
        $this->assertTrue($user->hasRole('Super Admin'));
    }

    /**
     * 测试撤销用户权限
     */
    public function test_revoke_user_permission(): void
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'edit.users']);

        $user->givePermissionTo($permission);
        $this->assertTrue($user->hasPermissionTo('edit.users'));

        $user->revokePermissionTo($permission);
        $this->assertFalse($user->hasPermissionTo('edit.users'));
    }

    /**
     * 测试撤销用户角色
     */
    public function test_revoke_user_role(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin']);

        $user->assignRole($role);
        $this->assertTrue($user->hasRole('admin'));

        $user->removeRole($role);
        $this->assertFalse($user->hasRole('admin'));
    }

    /**
     * 测试同步用户角色
     */
    public function test_sync_user_roles(): void
    {
        $user = User::factory()->create();
        $role1 = Role::create(['name' => 'admin']);
        $role2 = Role::create(['name' => 'editor']);

        $user->assignRole($role1);
        $this->assertTrue($user->hasRole('admin'));

        $user->syncRoles([$role2]);
        $this->assertFalse($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
    }

    /**
     * 测试同步用户权限
     */
    public function test_sync_user_permissions(): void
    {
        $user = User::factory()->create();
        $permission1 = Permission::create(['name' => 'edit.users']);
        $permission2 = Permission::create(['name' => 'delete.users']);

        $user->givePermissionTo($permission1);
        $this->assertTrue($user->hasPermissionTo('edit.users'));

        $user->syncPermissions([$permission2]);
        $this->assertFalse($user->hasPermissionTo('edit.users'));
        $this->assertTrue($user->hasPermissionTo('delete.users'));
    }

    /**
     * 测试获取用户的所有权限
     */
    public function test_get_all_user_permissions(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin']);
        $permission1 = Permission::create(['name' => 'edit.users']);
        $permission2 = Permission::create(['name' => 'delete.users']);

        $role->givePermissionTo($permission1);
        $user->assignRole($role);
        $user->givePermissionTo($permission2);

        $permissions = $user->getAllPermissions();

        $this->assertCount(2, $permissions);
        $this->assertTrue($permissions->contains('name', 'edit.users'));
        $this->assertTrue($permissions->contains('name', 'delete.users'));
    }

    /**
     * 测试检查多个权限（OR 逻辑）
     */
    public function test_check_multiple_permissions_or_logic(): void
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'edit.users']);

        $user->givePermissionTo($permission);

        // 用户拥有 edit.users 权限，应该返回 true
        $this->assertTrue($user->hasAnyPermission(['edit.users', 'delete.users']));
    }

    /**
     * 测试检查所有权限（AND 逻辑）
     */
    public function test_check_all_permissions_and_logic(): void
    {
        $user = User::factory()->create();
        $permission1 = Permission::create(['name' => 'edit.users']);
        $permission2 = Permission::create(['name' => 'delete.users']);

        $user->givePermissionTo([$permission1, $permission2]);

        // 用户拥有所有权限，应该返回 true
        $this->assertTrue($user->hasAllPermissions(['edit.users', 'delete.users']));
    }

    /**
     * 测试权限缓存
     */
    public function test_permission_caching(): void
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'edit.users']);

        $user->givePermissionTo($permission);

        // 第一次检查
        $this->assertTrue($user->hasPermissionTo('edit.users'));

        // 清除缓存
        $user->forgetCachedPermissions();

        // 第二次检查
        $this->assertTrue($user->hasPermissionTo('edit.users'));
    }

    /**
     * 测试角色守卫
     */
    public function test_role_guard(): void
    {
        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $this->assertEquals('web', $role->guard_name);
    }

    /**
     * 测试权限守卫
     */
    public function test_permission_guard(): void
    {
        $permission = Permission::create([
            'name' => 'edit.users',
            'guard_name' => 'web',
        ]);

        $this->assertEquals('web', $permission->guard_name);
    }
}
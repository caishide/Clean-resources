<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 权限系统数据填充
 * 
 * 定义系统角色和权限
 */
class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 清除缓存
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 创建权限
        $permissions = [
            // 用户管理
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // 角色管理
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            
            // 权限管理
            'view permissions',
            'edit permissions',
            
            // 订单管理
            'view orders',
            'create orders',
            'edit orders',
            'delete orders',
            'approve orders',
            'reject orders',
            
            // 结算管理
            'view settlements',
            'execute weekly settlement',
            'execute quarterly settlement',
            'view settlement reports',
            
            // 调整管理
            'view adjustments',
            'create adjustments',
            'approve adjustments',
            'reject adjustments',
            
            // PV 台账管理
            'view pv ledger',
            'adjust pv',
            
            // 前端内容管理
            'view frontend content',
            'edit frontend content',
            'manage seo',
            
            // 系统设置
            'view settings',
            'edit settings',
            'view logs',
            
            // 财务管理
            'view transactions',
            'view withdrawals',
            'approve withdrawals',
            'reject withdrawals',
            
            // 报表查看
            'view reports',
            'export reports',
            
            // 会员管理
            'view members',
            'edit members',
            'activate members',
            'deactivate members',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        // 创建角色并分配权限
        
        // 超级管理员（拥有所有权限）
        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web'
        ]);
        $superAdmin->givePermissionTo(Permission::all());

        // 管理员（大部分权限，除了删除和系统设置）
        $admin = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web'
        ]);
        $admin->givePermissionTo([
            'view users', 'create users', 'edit users',
            'view roles', 'view permissions',
            'view orders', 'create orders', 'edit orders', 'approve orders',
            'view settlements', 'execute weekly settlement', 'view settlement reports',
            'view adjustments', 'create adjustments', 'approve adjustments',
            'view pv ledger',
            'view frontend content', 'edit frontend content', 'manage seo',
            'view settings',
            'view transactions', 'view withdrawals', 'approve withdrawals',
            'view reports', 'export reports',
            'view members', 'edit members', 'activate members', 'deactivate members',
        ]);

        // 结算管理员（结算相关权限）
        $settlementManager = Role::firstOrCreate([
            'name' => 'Settlement Manager',
            'guard_name' => 'web'
        ]);
        $settlementManager->givePermissionTo([
            'view settlements', 'execute weekly settlement', 'execute quarterly settlement', 'view settlement reports',
            'view adjustments', 'create adjustments',
            'view pv ledger',
            'view reports',
        ]);

        // 财务管理员（财务相关权限）
        $financeManager = Role::firstOrCreate([
            'name' => 'Finance Manager',
            'guard_name' => 'web'
        ]);
        $financeManager->givePermissionTo([
            'view orders', 'approve orders', 'reject orders',
            'view transactions', 'view withdrawals', 'approve withdrawals', 'reject withdrawals',
            'view reports', 'export reports',
        ]);

        // 内容管理员（前端内容管理权限）
        $contentManager = Role::firstOrCreate([
            'name' => 'Content Manager',
            'guard_name' => 'web'
        ]);
        $contentManager->givePermissionTo([
            'view frontend content', 'edit frontend content', 'manage seo',
        ]);

        // 客服（查看权限）
        $support = Role::firstOrCreate([
            'name' => 'Support',
            'guard_name' => 'web'
        ]);
        $support->givePermissionTo([
            'view users',
            'view orders',
            'view settlements',
            'view adjustments',
            'view pv ledger',
            'view transactions',
            'view members',
        ]);

        // 普通用户（基础权限）
        $user = Role::firstOrCreate([
            'name' => 'User',
            'guard_name' => 'web'
        ]);
        $user->givePermissionTo([
            'view orders',
            'view transactions',
        ]);

        $this->command->info('权限和角色创建完成！');
    }
}
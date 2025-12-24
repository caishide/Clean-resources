<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // 强制测试环境使用内存 sqlite，避免读取生产配置或缓存的 MySQL 连接
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        // 禁用慢查询日志（避免文件权限问题）
        config()->set('querylog.enabled', false);
    }
}

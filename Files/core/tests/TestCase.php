<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // 强制测试环境使用内存 sqlite
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        // 禁用慢查询日志
        config()->set('querylog.enabled', false);

        // 运行迁移以创建测试数据库表
        $this->artisan('migrate');
    }

    /**
     * 准备测试数据库。
     */
    protected function prepareDatabase(): void
    {
        $this->artisan('migrate');
    }
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // 强制测试环境使用内存 sqlite，避免读取生产配置或缓存的 MySQL 连接
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
    }
}

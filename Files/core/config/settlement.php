<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 结算配置
    |--------------------------------------------------------------------------
    |
    | 此配置文件包含周结算和季度分红的所有相关配置
    |
    */

    // PV 配置
    'pv_unit_amount' => env('PV_UNIT_AMOUNT', 3000),
    
    // 奖金配置
    'pair_rate' => env('PAIR_RATE', 0.10),
    'pair_unit_amount' => env('PAIR_UNIT_AMOUNT', 300.0),
    
    // 拨出比例
    'total_cap_rate' => env('TOTAL_CAP_RATE', 0.7),        // 70%总拨出
    'global_reserve_rate' => env('GLOBAL_RESERVE_RATE', 0.04), // 4%功德池
    
    // 结转模式
    'carry_flash_mode' => env('CARRY_FLASH_MODE', 0),
    
    // 周封顶配置
    'pair_cap' => [
        0 => 0,       // 无职级
        1 => 10000,   // 一级
        2 => 20000,   // 二级
        3 => 30000,   // 三级
        4 => 50000,   // 四级
        5 => 100000,  // 五级
    ],
    
    // 管理奖比例
    'management_rates' => [
        '1-3' => 0.10,  // 1-3代 10%
        '4-5' => 0.05,  // 4-5代 5%
    ],
    
    // 季度分红配置
    'pool_stockist_rate' => env('POOL_STOCKIST_RATE', 0.01),  // 消费商池 1%
    'pool_leader_rate' => env('POOL_LEADER_RATE', 0.03),      // 领导人池 3%
    
    // 消费商池资格
    'stockist_min_purchases' => env('STOCKIST_MIN_PURCHASES', 3), // 最少请购次数
    
    // 领导人池资格
    'leader_min_weak_pv' => env('LEADER_MIN_WEAK_PV', 10000), // 最小弱区PV
    
    // 缓存配置
    'cache' => [
        'placement_chain_ttl' => env('PLACEMENT_CHAIN_TTL', 1440), // 24小时(分钟)
        'downlines_ttl' => env('DOWNLINES_TTL', 360),              // 6小时(分钟)
    ],
    
    // 分布式锁配置
    'lock' => [
        'settlement_timeout' => env('SETTLEMENT_LOCK_TIMEOUT', 300), // 5分钟
        'carry_flash_timeout' => env('CARRY_FLASH_LOCK_TIMEOUT', 300), // 5分钟
    ],
];
<?php

return [
    'version' => 'v10.1',

    // 奖金比例
    'direct_rate' => 0.20,
    'level_pair_rate' => 0.25,
    'pair_rate' => 0.10, // 对碰单对金额 = 3000 * pair_rate

    // 管理奖比例
    'management_rates' => [
        '1-3' => 0.10,
        '4-5' => 0.05,
    ],

    // 周封顶（仅对碰）
    'pair_cap' => [
        1 => 3000.0,   // 初级
        2 => 10000.0,  // 中级
        3 => 36000.0,  // 高级
    ],

    // 功德池预留（周结算）
    'global_reserve_rate' => 0.04, // 4%

    // 季度分红
    'pool_stockist_rate' => 0.01, // 1%
    'pool_leader_rate' => 0.03,   // 3%
];

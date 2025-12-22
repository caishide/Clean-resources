<?php

return [
    // 七宝进阶职级配置
    'ranks' => [
        'liuli_xingzhe' => [
            'name' => '琉璃行者',
            'multiplier' => 1.0,
            'min_pv' => 300000,  // 30万PV
            'required_direct_refs' => 2,  // 直推2人
            'structure_requirement' => null,  // 架构要求
            'order' => 1,
        ],
        'huangjin_daoshi' => [
            'name' => '黄金导师',
            'multiplier' => 1.2,
            'min_pv' => 1000000,  // 100万PV
            'required_direct_refs' => null,  // 不限制直推人数
            'structure_requirement' => [
                'required_rank' => 'liuli_xingzhe',
                'required_count' => 2,  // 2条线各出1个琉璃行者
                'lines' => 2,
            ],
            'order' => 2,
        ],
        'manao_hufa' => [
            'name' => '玛瑙护法',
            'multiplier' => 1.4,
            'min_pv' => 3000000,  // 300万PV
            'required_direct_refs' => null,
            'structure_requirement' => [
                'required_rank' => 'huangjin_daoshi',
                'required_count' => 2,
                'lines' => 2,
            ],
            'order' => 3,
        ],
        'moni_dade' => [
            'name' => '摩尼大德',
            'multiplier' => 1.7,
            'min_pv' => 10000000,  // 1000万PV
            'required_direct_refs' => null,
            'structure_requirement' => [
                'required_rank' => 'manao_hufa',
                'required_count' => 3,
                'lines' => 3,
            ],
            'order' => 4,
        ],
        'jingang_zunzhe' => [
            'name' => '金刚尊者',
            'multiplier' => 2.0,
            'min_pv' => 30000000,  // 3000万PV
            'required_direct_refs' => null,
            'structure_requirement' => [
                'required_rank' => 'moni_dade',
                'required_count' => 3,
                'lines' => 3,
            ],
            'order' => 5,
        ],
    ],

    // 职级顺序（用于排序和判断）
    'rank_order' => [
        'liuli_xingzhe',
        'huangjin_daoshi',
        'manao_hufa',
        'moni_dade',
        'jingang_zunzhe',
    ],

    // 职级晋升检查配置
    'promotion' => [
        'auto_promote' => true,  // 是否自动晋升
        'check_frequency' => 'daily',  // 检查频率：daily/weekly/monthly
        'update_timestamps' => true,  // 是否更新时间戳
    ],

    // 缓存配置
    'cache' => [
        'user_rank_ttl' => 3600,  // 用户职级缓存时间（1小时）
        'rank_structure_ttl' => 1800,  // 架构结构缓存时间（30分钟）
    ],
];
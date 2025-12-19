<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

class BonusConfigService
{
    /**
     * 获取合并后的奖金配置（DB bonus_config 优先，默认使用 config/bonus.php）
     */
    public function get(): array
    {
        $defaults = config('bonus', []);

        if (app()->environment('testing') && !Schema::hasTable('general_settings')) {
            return $defaults;
        }

        $gs = function_exists('gs') ? gs() : null;
        $dbConfig = [];
        if ($gs && isset($gs->bonus_config) && $gs->bonus_config) {
            $dbConfig = is_array($gs->bonus_config) ? $gs->bonus_config : json_decode($gs->bonus_config, true);
        }

        return array_replace_recursive($defaults, $dbConfig ?: []);
    }

    /**
     * 保存配置到 general_settings
     */
    public function save(array $config): void
    {
        if (!function_exists('gs')) {
            return;
        }
        $gs = gs();
        $gs->bonus_config = $config;
        $gs->save();
    }
}

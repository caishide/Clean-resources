<?php

namespace App\Services;

use App\Models\BonusConfig;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class BonusConfigService
{
    /**
     * 获取合并后的奖金配置（DB bonus_config 优先，默认使用 config/bonus.php）
     */
    public function get(?int $versionId = null): array
    {
        $defaults = config('bonus', []);

        if (app()->environment('testing') && !Schema::hasTable('bonus_configs') && !Schema::hasTable('general_settings')) {
            return $defaults;
        }

        $version = $this->getVersionRecord($versionId);
        if ($version) {
            $config = $this->decodeConfig($version->config_json);
            $config['version'] = $version->version_code;
            return array_replace_recursive($defaults, $config);
        }

        $gs = function_exists('gs') ? gs() : null;
        $dbConfig = [];
        if ($gs && isset($gs->bonus_config) && $gs->bonus_config) {
            $dbConfig = is_array($gs->bonus_config) ? $gs->bonus_config : json_decode($gs->bonus_config, true);
        }

        return array_replace_recursive($defaults, $dbConfig ?: []);
    }

    public function getVersions()
    {
        if (!Schema::hasTable('bonus_configs')) {
            return collect();
        }

        return BonusConfig::orderByDesc('id')->get();
    }

    public function getActiveVersion(): ?BonusConfig
    {
        if (!Schema::hasTable('bonus_configs')) {
            return null;
        }

        return BonusConfig::where('is_active', true)->orderByDesc('id')->first();
    }

    public function getVersionById(int $versionId): ?BonusConfig
    {
        if (!Schema::hasTable('bonus_configs')) {
            return null;
        }

        return BonusConfig::where('id', $versionId)->first();
    }

    public function createVersion(string $versionCode, array $config, ?int $createdBy = null, bool $activate = false): BonusConfig
    {
        $record = BonusConfig::create([
            'version_code' => $versionCode,
            'config_json' => $config,
            'is_active' => false,
            'created_by' => $createdBy,
        ]);

        if ($activate) {
            $this->activateVersion($record);
        } else {
            $this->syncLegacyConfig($record->config_json);
        }

        return $record;
    }

    public function updateVersion(BonusConfig $record, array $config, bool $activate = false): BonusConfig
    {
        $record->config_json = $config;
        $record->save();

        if ($activate) {
            $this->activateVersion($record);
        } else {
            $this->syncLegacyConfig($record->config_json);
        }

        return $record;
    }

    public function activateVersion(BonusConfig $record): void
    {
        if (!Schema::hasTable('bonus_configs')) {
            return;
        }

        DB::transaction(function () use ($record) {
            BonusConfig::where('is_active', true)->update(['is_active' => false]);
            $record->is_active = true;
            $record->activated_at = now();
            $record->save();
        });

        $this->syncLegacyConfig($record->config_json);
    }

    /**
     * 保存配置到 general_settings
     */
    public function save(array $config): void
    {
        $this->syncLegacyConfig($config);
    }

    private function getVersionRecord(?int $versionId = null): ?BonusConfig
    {
        if (!Schema::hasTable('bonus_configs')) {
            return null;
        }

        if ($versionId) {
            return BonusConfig::where('id', $versionId)->first();
        }

        return BonusConfig::where('is_active', true)->orderByDesc('id')->first();
    }

    private function decodeConfig($config): array
    {
        if (is_array($config)) {
            return $config;
        }
        if (is_string($config)) {
            return json_decode($config, true) ?: [];
        }
        return [];
    }

    private function syncLegacyConfig(array $config): void
    {
        if (!function_exists('gs')) {
            return;
        }

        $gs = gs();
        $gs->bonus_config = $config;
        $gs->save();
    }
}

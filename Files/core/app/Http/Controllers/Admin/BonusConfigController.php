<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BonusConfigService;
use Illuminate\Http\Request;

class BonusConfigController extends Controller
{
    public function index(BonusConfigService $bonusConfigService)
    {
        $pageTitle = '奖金参数配置';
        $config = $bonusConfigService->get();
        $defaults = config('bonus', []);

        return view('admin.setting.bonus_config', compact('pageTitle', 'config', 'defaults'));
    }

    public function update(Request $request, BonusConfigService $bonusConfigService)
    {
        $current = $bonusConfigService->get();

        $request->validate([
            'direct_rate' => 'required|numeric|min:0|max:1',
            'level_pair_rate' => 'required|numeric|min:0|max:1',
            'pair_rate' => 'required|numeric|min:0|max:1',
            'management_rate_first' => 'required|numeric|min:0|max:1',
            'management_rate_second' => 'required|numeric|min:0|max:1',
            'pair_cap_1' => 'required|numeric|min:0',
            'pair_cap_2' => 'required|numeric|min:0',
            'pair_cap_3' => 'required|numeric|min:0',
            'global_reserve_rate' => 'required|numeric|min:0|max:1',
            'pool_stockist_rate' => 'required|numeric|min:0|max:1',
            'pool_leader_rate' => 'required|numeric|min:0|max:1',
        ]);

        $config = [
            'version' => $current['version'] ?? config('bonus.version', 'v10.1'),
            'direct_rate' => (float) $request->direct_rate,
            'level_pair_rate' => (float) $request->level_pair_rate,
            'pair_rate' => (float) $request->pair_rate,
            'management_rates' => [
                '1-3' => (float) $request->management_rate_first,
                '4-5' => (float) $request->management_rate_second,
            ],
            'pair_cap' => [
                1 => (float) $request->pair_cap_1,
                2 => (float) $request->pair_cap_2,
                3 => (float) $request->pair_cap_3,
            ],
            'global_reserve_rate' => (float) $request->global_reserve_rate,
            'pool_stockist_rate' => (float) $request->pool_stockist_rate,
            'pool_leader_rate' => (float) $request->pool_leader_rate,
        ];

        $merged = array_replace_recursive(config('bonus', []), $config);
        $bonusConfigService->save($merged);

        $notify[] = ['success', __('admin.bonus_config.updated')];
        return back()->withNotify($notify);
    }
}

<?php

namespace App\Console\Commands;

use App\Constants\Status;
use App\Models\Order;
use App\Models\PendingBonus;
use App\Models\Product;
use App\Models\PvLedger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserExtra;
use App\Services\SettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 模拟一周内批量发货，验证 PV/直推/层碰/周结算链路。
 * 默认在单个 DB 事务中执行，结束时回滚，不污染真实数据。
 */
class SimulateWeeklyBonuses extends Command
{
    protected $signature = 'simulate:weekly-demo
        {--orders=200 : 模拟订单数}
        {--depth=5 : 二叉安置树深度（含根）}';

    protected $description = '模拟批量发货并执行周结算（默认事务回滚，仅用于验证）';

    public function handle(SettlementService $settlementService): int
    {
        $ordersCount = (int) $this->option('orders');
        $depth = (int) $this->option('depth');

        $sim = app(\App\Services\SimulationService::class);
        $result = $sim->simulate([
            'weeks' => 1,
            'total_users' => max(20, $ordersCount + 5),
            'weekly_new_users' => max(10, $ordersCount / $depth),
            'left_ratio' => 50,
            'orders_per_week' => $ordersCount,
            'persist' => false,
        ]);

        $week = $result['weeks'][0] ?? [];

        $this->info("模拟完成（已回滚数据库，不留痕迹）");
        $this->table(['指标', '数值'], [
            ['PV Ledger 行数', PvLedger::count()],
            ['交易总数', Transaction::count()],
            ['直推已发', $week['direct_paid'] ?? 0],
            ['层碰已发', $week['level_pair_paid'] ?? 0],
            ['对碰已发', $week['pair_paid'] ?? 0],
            ['管理已发', $week['matching_paid'] ?? 0],
            ['待处理奖金数', PendingBonus::count()],
            ['用户数', $result['total_users'] ?? 'N/A'],
        ]);

        if (!empty($week)) {
            $this->newLine();
            $this->info('周结预演结果');
            $this->table(['字段', '值'], [
                ['week_key', $week['week_key'] ?? ''],
                ['total_pv', $week['total_pv'] ?? 0],
                ['k_factor', $week['k_factor'] ?? 0],
                ['direct_paid', $week['direct_paid'] ?? 0],
                ['level_pair_paid', $week['level_pair_paid'] ?? 0],
                ['pair_paid', $week['pair_paid'] ?? 0],
                ['matching_paid', $week['matching_paid'] ?? 0],
            ]);
        }

        return Command::SUCCESS;
    }
}

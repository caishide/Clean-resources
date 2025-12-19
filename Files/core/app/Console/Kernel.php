<?php

namespace App\Console;

use App\Console\Commands\WeeklySettlementCommand;
use App\Console\Commands\QuarterlySettlementCommand;
use App\Console\Commands\FinalizeAdjustmentBatchCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        WeeklySettlementCommand::class,
        QuarterlySettlementCommand::class,
        FinalizeAdjustmentBatchCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // 周结算：每周一 02:00 执行上一周结算
        $schedule->command('settlement:weekly')->weeklyOn(1, '2:00');

        // 季度结算：1/4/7/10 月 15 日 03:00 结算上一季度
        $schedule->command('settlement:quarterly')->cron('0 3 15 1,4,7,10 *');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}

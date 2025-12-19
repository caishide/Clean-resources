<?php
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\UserExtra;
use App\Models\Transaction;
use App\Models\PvLedger;
use App\Models\UserPointsLog;
use App\Models\PendingBonus;
use App\Constants\Status;
use App\Services\SettlementService;
use App\Services\AdjustmentService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();
try {
    $product = Product::first();
    if (!$product) {
        $product = Product::create([
            'name' => 'Test Lotus',
            'price' => 3000,
            'bv' => 3000,
            'quantity' => 1000,
            'status' => Status::ENABLE,
        ]);
    }

    $root = User::create([
        'username' => 'test_root_' . Str::random(4),
        'email' => Str::random(8) . '@test.local',
        'password' => bcrypt('password'),
        'plan_id' => 1,
        'pos_id' => 0,
        'position' => 0,
        'ref_by' => 0,
        'status' => Status::USER_ACTIVE,
        'ev' => Status::VERIFIED,
        'sv' => Status::VERIFIED,
        'balance' => 0,
    ]);
    UserExtra::firstOrCreate(['user_id' => $root->id]);

    $left = User::create([
        'username' => 'test_left_' . Str::random(4),
        'email' => Str::random(8) . '@test.local',
        'password' => bcrypt('password'),
        'plan_id' => 1,
        'pos_id' => $root->id,
        'position' => Status::LEFT,
        'ref_by' => $root->id,
        'status' => Status::USER_ACTIVE,
        'ev' => Status::VERIFIED,
        'sv' => Status::VERIFIED,
        'balance' => 0,
    ]);
    UserExtra::firstOrCreate(['user_id' => $left->id]);

    $right = User::create([
        'username' => 'test_right_' . Str::random(4),
        'email' => Str::random(8) . '@test.local',
        'password' => bcrypt('password'),
        'plan_id' => 1,
        'pos_id' => $root->id,
        'position' => Status::RIGHT,
        'ref_by' => $root->id,
        'status' => Status::USER_ACTIVE,
        'ev' => Status::VERIFIED,
        'sv' => Status::VERIFIED,
        'balance' => 0,
    ]);
    UserExtra::firstOrCreate(['user_id' => $right->id]);

    // 左订单（结算前退款）
    $o1 = Order::create([
        'user_id' => $left->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'total_price' => 3000,
        'trx' => 'TESTO1' . Str::random(4),
        'status' => Status::ORDER_PENDING,
    ]);
    $o1->status = Status::ORDER_SHIPPED; $o1->save();

    // 右订单（结算后退款）
    $o2 = Order::create([
        'user_id' => $right->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'total_price' => 3000,
        'trx' => 'TESTO2' . Str::random(4),
        'status' => Status::ORDER_PENDING,
    ]);
    $o2->status = Status::ORDER_SHIPPED; $o2->save();

    $adjService = app(AdjustmentService::class);
    $batchBefore = $adjService->createRefundAdjustment($o1, 'before finalize');

    $week = now()->format('o-\\WW');
    // 清理同周结算记录，便于重复跑测试
    DB::table('weekly_settlement_user_summaries')->where('week_key', $week)->delete();
    DB::table('weekly_settlements')->where('week_key', $week)->delete();

    $settle = app(SettlementService::class);
    $weeklyRes = $settle->executeWeeklySettlement($week, false);

    $batchAfter = $adjService->createRefundAdjustment($o2, 'after finalize');
    $adjService->finalizeAdjustmentBatch($batchAfter->id);

    DB::commit();

    $summary = [
        'users' => [
            'root' => ['id' => $root->id, 'username' => $root->username, 'balance' => $root->fresh()->balance],
            'left' => ['id' => $left->id, 'username' => $left->username, 'balance' => $left->fresh()->balance],
            'right' => ['id' => $right->id, 'username' => $right->username, 'balance' => $right->fresh()->balance],
        ],
        'weekly' => $weeklyRes,
        'pv_ledger_counts' => PvLedger::whereIn('source_id', [$o1->trx, $o2->trx, $week])->count(),
        'points_logs' => UserPointsLog::whereIn('source_id', [$o1->trx, $o2->trx, $batchAfter->batch_key])->count(),
        'transactions' => Transaction::whereIn('source_id', [$o1->trx, $o2->trx, $week, $batchAfter->batch_key])->count(),
        'pending_bonuses' => PendingBonus::count(),
        'batches' => [$batchBefore->batch_key, $batchAfter->batch_key],
        'orders' => ['o1' => $o1->trx, 'o2' => $o2->trx],
    ];
    echo json_encode($summary, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    DB::rollBack();
    echo 'ERROR: ' . $e->getMessage();
}

<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Models\AdjustmentBatch;
use App\Models\PendingBonus;
use App\Models\PvLedger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserExtra;
use App\Models\UserPointsLog;
use App\Models\WithdrawMethod;
use App\Repositories\BonusRepository;
use App\Services\AdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    /** @test */
    public function manual_pending_bonus_can_be_released_and_updates_balance()
    {
        $user = User::factory()->create(['balance' => 0, 'plan_id' => 1]);
        UserExtra::factory()->create(['user_id' => $user->id]);
        $bonus = PendingBonus::create([
            'recipient_id' => $user->id,
            'bonus_type' => 'direct',
            'amount' => 100,
            'source_type' => 'order',
            'source_id' => 'TST1',
            'accrued_week_key' => '2025-W01',
            'status' => 'pending',
            'release_mode' => 'manual',
        ]);

        $repo = app(BonusRepository::class);
        $result = $repo->batchReleasePendingBonuses([$bonus->id]);

        $bonus->refresh();
        $user->refresh();

        $this->assertEquals('released', $bonus->status);
        $this->assertEquals(100, $user->balance);
        $this->assertEquals('success', $result[$bonus->id]['status']);
    }

    /** @test */
    public function adjustment_finalize_creates_reversal_entries()
    {
        $user = User::factory()->create(['plan_id' => 1, 'ref_by' => 0, 'pos_id' => 0, 'position' => 0]);
        UserExtra::factory()->create(['user_id' => $user->id]);

        $trx = 'ORD-TEST';
        PvLedger::create([
            'user_id' => $user->id,
            'from_user_id' => null,
            'position' => 1,
            'level' => 1,
            'amount' => 3000,
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => $trx,
        ]);
        Transaction::create([
            'user_id' => $user->id,
            'trx_type' => '+',
            'amount' => 100,
            'charge' => 0,
            'post_balance' => 100,
            'remark' => 'direct_bonus',
            'source_type' => 'order',
            'source_id' => $trx,
        ]);
        UserPointsLog::create([
            'user_id' => $user->id,
            'source_type' => 'PURCHASE',
            'source_id' => $trx,
            'points' => 3000,
        ]);

        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-TEST',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => $trx,
            'snapshot' => json_encode([]),
        ]);

        $service = app(AdjustmentService::class);
        $service->finalizeAdjustmentBatch($batch->id);

        $this->assertDatabaseHas('adjustment_entries', ['batch_id' => $batch->id, 'asset_type' => 'pv']);
        $this->assertDatabaseHas('pv_ledger', ['source_type' => 'adjustment', 'source_id' => $batch->batch_key]);
        $this->assertDatabaseHas('user_points_log', ['source_type' => 'PURCHASE_REVERSAL', 'source_id' => $batch->batch_key]);
        $this->assertDatabaseHas('transactions', ['remark' => 'direct_bonus_reversal', 'source_id' => $batch->batch_key]);
    }

    /** @test */
    public function withdraw_is_blocked_when_balance_negative()
    {
        $user = User::factory()->create(['balance' => -10, 'plan_id' => 1]);
        $method = WithdrawMethod::factory()->create([
            'min_limit' => 1,
            'max_limit' => 1000,
            'fixed_charge' => 0,
            'percent_charge' => 0,
            'status' => Status::ENABLE,
        ]);

        $response = $this->actingAs($user)->post(route('user.withdraw.money'), [
            'method_code' => $method->id,
            'amount' => 10,
        ]);

        $response->assertSessionHas('notify');
        $this->assertEquals(-10, $user->fresh()->balance);
    }
}

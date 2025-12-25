<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Models\Admin;
use App\Models\AdjustmentBatch;
use App\Models\AdjustmentEntry;
use App\Models\User;
use Tests\TestCase;

class AdjustmentBatchSecurityTest extends TestCase
{
    
    protected $admin;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'admin_access' => 1,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'firstname' => 'Test',
            'lastname' => 'User',
            'username' => 'testuser',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
            'status' => Status::USER_ACTIVE,
            'ev' => Status::VERIFIED,
            'sv' => Status::VERIFIED,
        ]);
    }

    /** @test */
    public function admin_can_view_adjustment_batches()
    {
        AdjustmentBatch::create([
            'batch_key' => 'TEST-001',
            'reason_type' => 'manual_correction',
            'reference_type' => 'order',
            'reference_id' => '1',
            'snapshot' => json_encode(['test' => 'data']),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.adjustment.batches'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.adjustment.batches');
    }

    /** @test */
    public function admin_can_filter_batches_by_status()
    {
        AdjustmentBatch::create([
            'batch_key' => 'TEST-001',
            'reason_type' => 'manual_correction',
            'reference_type' => 'order',
            'reference_id' => '1',
            'snapshot' => json_encode(['test' => 'data']),
            'status' => 'pending',
        ]);

        AdjustmentBatch::create([
            'batch_key' => 'TEST-002',
            'reason_type' => 'manual_correction',
            'reference_type' => 'order',
            'reference_id' => '2',
            'snapshot' => json_encode(['test' => 'data']),
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.adjustment.batches') . '?status=pending');

        $response->assertStatus(200);
    }

    /** @test */
    public function admin_can_view_batch_details()
    {
        $batch = AdjustmentBatch::create([
            'batch_key' => 'TEST-001',
            'reason_type' => 'manual_correction',
            'reference_type' => 'order',
            'reference_id' => '1',
            'snapshot' => json_encode(['test' => 'data']),
            'status' => 'pending',
        ]);

        AdjustmentEntry::create([
            'batch_id' => $batch->id,
            'user_id' => $this->user->id,
            'amount' => 100,
            'asset_type' => 'wallet',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.adjustment.show', $batch->id));

        $response->assertStatus(200);
        $response->assertViewIs('admin.adjustment.show');
    }

    /** @test */
    public function admin_cannot_finalize_nonexistent_batch()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.adjustment.finalize', 9999));

        $response->assertStatus(404);
    }

    /** @test */
    public function admin_cannot_finalize_already_finalized_batch()
    {
        $batch = AdjustmentBatch::create([
            'batch_key' => 'TEST-001',
            'reason_type' => 'manual_correction',
            'reference_type' => 'order',
            'reference_id' => '1',
            'snapshot' => json_encode(['test' => 'data']),
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.adjustment.finalize', $batch->id));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function admin_can_finalize_pending_batch()
    {
        $batch = AdjustmentBatch::create([
            'batch_key' => 'TEST-001',
            'reason_type' => 'manual_correction',
            'reference_type' => 'order',
            'reference_id' => '1',
            'snapshot' => json_encode(['test' => 'data']),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.adjustment.finalize', $batch->id));

        $response->assertStatus(302);
        $response->assertSessionHas('notify');

        $this->assertDatabaseHas('adjustment_batches', [
            'id' => $batch->id,
            'finalized_by' => $this->admin->id,
        ]);
    }

    /** @test */
    public function batch_finalization_creates_audit_log()
    {
        $batch = AdjustmentBatch::create([
            'batch_key' => 'TEST-001',
            'reason_type' => 'manual_correction',
            'reference_type' => 'order',
            'reference_id' => '1',
            'snapshot' => json_encode(['test' => 'data']),
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.adjustment.finalize', $batch->id));

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action_type' => 'adjustment_finalize',
            'entity_type' => 'adjustment_batch',
            'entity_id' => $batch->id,
        ]);
    }

    /** @test */
    public function non_admin_cannot_access_adjustment_batches()
    {
        $regularAdmin = Admin::create([
            'name' => 'Regular',
            'email' => 'regular@test.com',
            'username' => 'regular',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'admin_access' => 0,
        ]);

        $response = $this->actingAs($regularAdmin, 'admin')
            ->get(route('admin.adjustment.batches'));

        $response->assertStatus(403);
    }

    /** @test */
    public function adjustment_batch_pagination_works()
    {
        // Create multiple batches
        for ($i = 1; $i <= 25; $i++) {
            AdjustmentBatch::create([
                'batch_key' => 'TEST-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'reason_type' => 'manual_correction',
                'reference_type' => 'order',
                'reference_id' => (string) $i,
                'snapshot' => json_encode(['test' => 'data']),
                'status' => 'pending',
            ]);
        }

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.adjustment.batches'));

        $response->assertStatus(200);
    }

    /** @test */
    public function batch_entry_pagination_works()
    {
        $batch = AdjustmentBatch::create([
            'batch_key' => 'TEST-001',
            'reason_type' => 'manual_correction',
            'reference_type' => 'order',
            'reference_id' => '1',
            'snapshot' => json_encode(['test' => 'data']),
            'status' => 'pending',
        ]);

        // Create multiple entries
        for ($i = 1; $i <= 60; $i++) {
            AdjustmentEntry::create([
                'batch_id' => $batch->id,
                'user_id' => $this->user->id,
                'amount' => 100,
                'asset_type' => 'wallet',
            ]);
        }

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.adjustment.show', $batch->id));

        $response->assertStatus(200);
    }
}

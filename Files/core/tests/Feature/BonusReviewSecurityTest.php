<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Models\Admin;
use App\Models\PendingBonus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BonusReviewSecurityTest extends TestCase
{
    use RefreshDatabase;

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

        // Create some pending bonuses
        PendingBonus::create([
            'recipient_id' => $this->user->id,
            'bonus_type' => 'direct_bonus',
            'amount' => 100,
            'status' => 'pending',
            'release_mode' => 'manual',
        ]);

        PendingBonus::create([
            'recipient_id' => $this->user->id,
            'bonus_type' => 'level_bonus',
            'amount' => 50,
            'status' => 'pending',
            'release_mode' => 'manual',
        ]);
    }

    /** @test */
    public function admin_can_view_pending_bonuses()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.bonus.review'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.bonus.pending');
    }

    /** @test */
    public function admin_cannot_approve_without_selecting_bonuses()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.bonus.approve'), []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function admin_can_approve_selected_bonuses()
    {
        $bonusIds = PendingBonus::where('status', 'pending')->pluck('id')->toArray();

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.bonus.approve'), [
                'bonus_ids' => $bonusIds,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('notify');
    }

    /** @test */
    public function admin_cannot_reject_nonexistent_bonus()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.bonus.reject', 9999));

        $response->assertStatus(404);
    }

    /** @test */
    public function admin_cannot_reject_already_processed_bonus()
    {
        $bonus = PendingBonus::first();
        $bonus->status = 'approved';
        $bonus->save();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.bonus.reject', $bonus->id));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function admin_can_reject_pending_bonus()
    {
        $bonus = PendingBonus::where('status', 'pending')->first();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.bonus.reject', $bonus->id));

        $response->assertStatus(302);
        $response->assertSessionHas('notify');

        $this->assertDatabaseHas('pending_bonuses', [
            'id' => $bonus->id,
            'status' => 'rejected',
        ]);
    }

    /** @test */
    public function bonus_release_creates_audit_log()
    {
        $bonus = PendingBonus::where('status', 'pending')->first();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.bonus.reject', $bonus->id));

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action_type' => 'pending_bonus_reject',
            'entity_type' => 'pending_bonus',
            'entity_id' => $bonus->id,
        ]);
    }

    /** @test */
    public function non_admin_cannot_access_bonus_review()
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
            ->get(route('admin.bonus.review'));

        $response->assertStatus(403);
    }

    /** @test */
    public function bonus_filter_by_status_works()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.bonus.review') . '?status=pending');

        $response->assertStatus(200);
        $response->assertViewIs('admin.bonus.pending');
    }

    /** @test */
    public function bonus_filter_by_release_mode_works()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.bonus.review') . '?release_mode=manual');

        $response->assertStatus(200);
        $response->assertViewIs('admin.bonus.pending');
    }
}

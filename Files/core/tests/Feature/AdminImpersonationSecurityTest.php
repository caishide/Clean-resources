<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminImpersonationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $targetUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'username' => 'admin',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
            'admin_access' => 1,
        ]);

        $this->targetUser = \App\Models\User::create([
            'name' => 'Test User',
            'firstname' => 'Test',
            'lastname' => 'User',
            'username' => 'testuser',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'status' => Status::USER_ACTIVE,
            'ev' => Status::VERIFIED,
            'sv' => Status::VERIFIED,
        ]);
    }

    /** @test */
    public function admin_cannot_impersonate_without_2fa()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.impersonate', $this->targetUser->id));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function admin_can_impersonate_with_valid_2fa()
    {
        // Set 2FA verification time (simulating recent 2FA verification)
        session(['twofa_verified_at' => now()]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.impersonate', $this->targetUser->id));

        $response->assertStatus(302);
        $this->assertTrue(session()->has('admin_impersonating'));
        $this->assertEquals(session('admin_impersonating'), $this->admin->id);
    }

    /** @test */
    public function impersonation_creates_audit_log()
    {
        session(['twofa_verified_at' => now()]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.impersonate', $this->targetUser->id));

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action_type' => 'admin_impersonation_start',
            'entity_type' => 'user',
            'entity_id' => $this->targetUser->id,
        ]);
    }

    /** @test */
    public function admin_cannot_impersonate_same_user_twice()
    {
        session(['twofa_verified_at' => now()]);

        // First impersonation
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.impersonate', $this->targetUser->id));

        // Second impersonation should fail
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.impersonate', $this->targetUser->id));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function admin_can_exit_impersonation()
    {
        session(['twofa_verified_at' => now(), 'admin_impersonating' => $this->admin->id]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.exit-impersonation'));

        $response->assertStatus(302);
        $this->assertFalse(session()->has('admin_impersonating'));
    }

    /** @test */
    public function exit_impersonation_creates_audit_log()
    {
        session(['twofa_verified_at' => now(), 'admin_impersonating' => $this->admin->id]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.exit-impersonation'));

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action_type' => 'admin_impersonation_end',
        ]);
    }

    /** @test */
    public function impersonation_expires_after_time_limit()
    {
        session(['twofa_verified_at' => now()->subHours(3), 'admin_impersonating' => $this->admin->id]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'));

        $response->assertStatus(302);
        $this->assertFalse(session()->has('admin_impersonating'));
    }

    /** @test */
    public function non_admin_cannot_access_impersonation_routes()
    {
        $regularAdmin = Admin::create([
            'name' => 'Regular Admin',
            'email' => 'regular@test.com',
            'username' => 'regular',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'admin_access' => 0, // No admin access
        ]);

        $response = $this->actingAs($regularAdmin, 'admin')
            ->post(route('admin.users.impersonate', $this->targetUser->id));

        $response->assertStatus(403);
    }
}

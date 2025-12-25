<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

/**
 * Admin模型单元测试
 *
 * 测试管理员模型的各种业务逻辑和关联关系
 */
class AdminTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'username',
            'name',
            'email',
            'password',
            'status',
            'email_verified_at',
        ];
        $this->assertEquals($fillable, (new Admin())->getFillable());
    }

    /** @test */
    public function it_has_correct_casts()
    {
        $casts = (new Admin())->getCasts();
        $this->assertArrayHasKey('email_verified_at', $casts);
        $this->assertArrayHasKey('password', $casts);
    }

    /** @test */
    public function it_has_hidden_attributes()
    {
        $hidden = (new Admin())->getHidden();
        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
    }

    /** @test */
    public function it_uses_correct_table_name()
    {
        $this->assertEquals('admins', (new Admin())->getTable());
    }

    /** @test */
    public function it_has_correct_primary_key()
    {
        $this->assertEquals('id', (new Admin())->getKeyName());
    }

    /** @test */
    public function it_has_correct_timestamp_columns()
    {
        $this->assertTrue((new Admin())->timestamps);
    }

    /** @test */
    public function it_can_create_admin()
    {
        $admin = Admin::create([
            'username' => 'admin1',
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'status' => 1,
        ]);

        $this->assertInstanceOf(Admin::class, $admin);
        $this->assertEquals('admin1', $admin->username);
        $this->assertEquals('Test Admin', $admin->name);
        $this->assertEquals('admin@test.com', $admin->email);
        $this->assertTrue(Hash::check('password123', $admin->password));
    }

    /** @test */
    public function it_can_check_if_admin_is_active()
    {
        $activeAdmin = Admin::factory()->create(['status' => 1]);
        $inactiveAdmin = Admin::factory()->create(['status' => 0]);

        $this->assertTrue($activeAdmin->isActive());
        $this->assertFalse($inactiveAdmin->isActive());
    }

    /** @test */
    public function it_can_check_if_admin_email_is_verified()
    {
        $verifiedAdmin = Admin::factory()->create([
            'email_verified_at' => now()
        ]);
        $unverifiedAdmin = Admin::factory()->create([
            'email_verified_at' => null
        ]);

        $this->assertTrue($verifiedAdmin->isEmailVerified());
        $this->assertFalse($unverifiedAdmin->isEmailVerified());
    }

    /** @test */
    public function it_has_display_name_accessor()
    {
        $admin = Admin::factory()->create([
            'name' => 'John Doe'
        ]);

        $displayName = $admin->displayName();

        $this->assertIsString($displayName);
    }

    /** @test */
    public function it_can_scope_active_admins()
    {
        Admin::factory()->count(3)->create(['status' => 1]);
        Admin::factory()->count(2)->create(['status' => 0]);

        $activeAdmins = Admin::active()->get();

        $this->assertCount(3, $activeAdmins);
    }

    /** @test */
    public function it_can_scope_verified_admins()
    {
        Admin::factory()->count(2)->create([
            'email_verified_at' => now()
        ]);
        Admin::factory()->count(3)->create([
            'email_verified_at' => null
        ]);

        $verifiedAdmins = Admin::verified()->get();

        $this->assertCount(2, $verifiedAdmins);
    }

    /** @test */
    public function it_can_scope_by_username()
    {
        Admin::factory()->create(['username' => 'admin1']);
        Admin::factory()->create(['username' => 'admin2']);

        $admin1 = Admin::byUsername('admin1')->first();
        $admin2 = Admin::byUsername('admin2')->first();

        $this->assertNotNull($admin1);
        $this->assertNotNull($admin2);
        $this->assertEquals('admin1', $admin1->username);
        $this->assertEquals('admin2', $admin2->username);
    }

    /** @test */
    public function it_can_scope_by_email()
    {
        $admin1 = Admin::factory()->create(['email' => 'admin1@test.com']);
        $admin2 = Admin::factory()->create(['email' => 'admin2@test.com']);

        $foundAdmin1 = Admin::byEmail('admin1@test.com')->first();
        $foundAdmin2 = Admin::byEmail('admin2@test.com')->first();

        $this->assertNotNull($foundAdmin1);
        $this->assertNotNull($foundAdmin2);
        $this->assertEquals($admin1->id, $foundAdmin1->id);
        $this->assertEquals($admin2->id, $foundAdmin2->id);
    }

    /** @test */
    public function it_can_update_admin_status()
    {
        $admin = Admin::factory()->create(['status' => 0]);

        $admin->updateStatus(1);

        $this->assertEquals(1, $admin->fresh()->status);
    }

    /** @test */
    public function it_can_verify_admin_email()
    {
        $admin = Admin::factory()->create([
            'email_verified_at' => null
        ]);

        $admin->verifyEmail();

        $this->assertNotNull($admin->fresh()->email_verified_at);
    }

    /** @test */
    public function it_can_change_password()
    {
        $admin = Admin::factory()->create([
            'password' => Hash::make('oldpassword')
        ]);

        $admin->changePassword('newpassword123');

        $admin->refresh();
        $this->assertTrue(Hash::check('newpassword123', $admin->password));
        $this->assertFalse(Hash::check('oldpassword', $admin->password));
    }

    /** @test */
    public function it_can_get_admin_last_login()
    {
        $admin = Admin::factory()->create();

        $lastLogin = $admin->lastLogin();

        $this->assertIsString($lastLogin);
    }

    /** @test */
    public function it_can_get_admin_permissions()
    {
        $admin = Admin::factory()->create();

        $permissions = $admin->permissions();

        $this->assertIsArray($permissions);
    }

    /** @test */
    public function it_can_check_if_admin_has_permission()
    {
        $admin = Admin::factory()->create();

        $hasPermission = $admin->hasPermission('view_dashboard');

        $this->assertIsBool($hasPermission);
    }

    /** @test */
    public function it_can_check_if_admin_has_role()
    {
        $admin = Admin::factory()->create();

        $hasRole = $admin->hasRole('admin');

        $this->assertIsBool($hasRole);
    }

    /** @test */
    public function it_can_get_admin_roles()
    {
        $admin = Admin::factory()->create();

        $roles = $admin->roles();

        $this->assertIsArray($roles);
    }

    /** @test */
    public function it_can_assign_role_to_admin()
    {
        $admin = Admin::factory()->create();

        $admin->assignRole('admin');

        $this->assertTrue($admin->hasRole('admin'));
    }

    /** @test */
    public function it_can_remove_role_from_admin()
    {
        $admin = Admin::factory()->create();
        $admin->assignRole('admin');

        $admin->removeRole('admin');

        $this->assertFalse($admin->hasRole('admin'));
    }

    /** @test */
    public function it_can_get_admin_activity_log()
    {
        $admin = Admin::factory()->create();

        $activityLog = $admin->activityLog();

        $this->assertIsCollection($activityLog);
    }

    /** @test */
    public function it_can_log_admin_activity()
    {
        $admin = Admin::factory()->create();

        $admin->logActivity('Logged in', 'login');

        $activityLog = $admin->activityLog();

        $this->assertCount(1, $activityLog);
        $this->assertEquals('Logged in', $activityLog->first()->description);
    }

    /** @test */
    public function it_can_get_admin_dashboard_data()
    {
        $admin = Admin::factory()->create();

        $dashboardData = $admin->dashboardData();

        $this->assertIsArray($dashboardData);
        $this->assertArrayHasKey('total_users', $dashboardData);
        $this->assertArrayHasKey('total_orders', $dashboardData);
        $this->assertArrayHasKey('total_revenue', $dashboardData);
    }

    /** @test */
    public function it_can_get_admin_statistics()
    {
        $admin = Admin::factory()->create();

        $stats = $admin->statistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('users_count', $stats);
        $this->assertArrayHasKey('orders_count', $stats);
        $this->assertArrayHasKey('revenue_total', $stats);
    }

    /** @test */
    public function it_can_get_admin_permissions_array()
    {
        $admin = Admin::factory()->create();

        $permissions = $admin->permissionsArray();

        $this->assertIsArray($permissions);
    }

    /** @test */
    public function it_can_check_if_admin_is_super_admin()
    {
        $admin = Admin::factory()->create();

        $isSuperAdmin = $admin->isSuperAdmin();

        $this->assertIsBool($isSuperAdmin);
    }

    /** @test */
    public function it_can_get_admin_name_with_username()
    {
        $admin = Admin::factory()->create([
            'name' => 'John Doe',
            'username' => 'johndoe'
        ]);

        $nameWithUsername = $admin->nameWithUsername();

        $this->assertIsString($nameWithUsername);
        $this->assertStringContainsString('John Doe', $nameWithUsername);
        $this->assertStringContainsString('johndoe', $nameWithUsername);
    }

    /** @test */
    public function it_can_get_admin_status_badge()
    {
        $admin = Admin::factory()->create(['status' => 1]);

        $statusBadge = $admin->statusBadge();

        $this->assertIsString($statusBadge);
    }

    /** @test */
    public function it_can_get_admin_email_verification_badge()
    {
        $admin = Admin::factory()->create([
            'email_verified_at' => now()
        ]);

        $emailBadge = $admin->emailVerificationBadge();

        $this->assertIsString($emailBadge);
    }
}

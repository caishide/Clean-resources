<?php

namespace Tests\Unit\Http\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Admin;
use App\Models\Transaction;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Order;
use App\Models\BvLog;
use App\Constants\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * ManageUsersController单元测试
 *
 * 测试用户管理控制器的各种功能
 */
class ManageUsersControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::factory()->create();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_displays_all_users()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.all'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.list');
        $response->assertViewHas('pageTitle', 'All Users');
        $response->assertViewHas('users');
    }

    /** @test */
    public function it_displays_active_users()
    {
        $activeUser = User::factory()->create(['status' => Status::USER_ACTIVE]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.active'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.list');
        $response->assertViewHas('pageTitle', 'Active Users');
    }

    /** @test */
    public function it_displays_banned_users()
    {
        $bannedUser = User::factory()->create(['status' => Status::USER_BAN]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.banned'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.list');
        $response->assertViewHas('pageTitle', 'Banned Users');
    }

    /** @test */
    public function it_displays_email_unverified_users()
    {
        $unverifiedUser = User::factory()->create(['ev' => Status::UNVERIFIED]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.email.unverified'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.list');
        $response->assertViewHas('pageTitle', 'Email Unverified Users');
    }

    /** @test */
    public function it_displays_kyc_unverified_users()
    {
        $unverifiedUser = User::factory()->create(['kv' => Status::KYC_UNVERIFIED]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.kyc.unverified'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.list');
        $response->assertViewHas('pageTitle', 'KYC Unverified Users');
    }

    /** @test */
    public function it_displays_user_details()
    {
        // 创建相关数据
        Deposit::factory()->create([
            'user_id' => $this->user->id,
            'status' => Status::PAYMENT_SUCCESS,
            'amount' => 100,
        ]);

        Withdrawal::factory()->create([
            'user_id' => $this->user->id,
            'status' => Status::PAYMENT_SUCCESS,
            'amount' => 50,
        ]);

        Transaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        Order::factory()->count(2)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.detail', $this->user->id));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.detail');
        $response->assertViewHas('user');
        $response->assertViewHas('pageTitle');
        $response->assertViewHas('totalDeposit');
        $response->assertViewHas('totalWithdrawals');
        $response->assertViewHas('totalTransaction');
        $response->assertViewHas('totalOrder');
    }

    /** @test */
    public function it_approves_kyc()
    {
        $user = User::factory()->create([
            'kv' => Status::KYC_PENDING,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.kyc.approve', $user->id));

        $response->assertRedirect();
        $response->assertSessionHas('notify');

        $user->refresh();
        $this->assertEquals(Status::KYC_VERIFIED, $user->kv);
    }

    /** @test */
    public function it_rejects_kyc()
    {
        $user = User::factory()->create([
            'kv' => Status::KYC_PENDING,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.kyc.reject', $user->id), [
                'reason' => 'Invalid documents',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('notify');

        $user->refresh();
        $this->assertEquals(Status::KYC_UNVERIFIED, $user->kv);
        $this->assertNotNull($user->kyc_rejection_reason);
    }

    /** @test */
    public function it_updates_user_details()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.update', $this->user->id), [
                'firstname' => 'John',
                'lastname' => 'Doe',
                'email' => 'john.doe@example.com',
                'mobile' => '1234567890',
                'country' => 'US',
                'ev' => 1,
                'sv' => 1,
                'ts' => 1,
                'kv' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('notify');

        $this->user->refresh();
        $this->assertEquals('John', $this->user->firstname);
        $this->assertEquals('Doe', $this->user->lastname);
        $this->assertEquals('john.doe@example.com', $this->user->email);
    }

    /** @test */
    public function it_adds_balance_to_user()
    {
        $initialBalance = $this->user->balance;

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.add.sub.balance', $this->user->id), [
                'amount' => 100,
                'act' => 'add',
                'remark' => 'Test balance addition',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('notify');

        $this->user->refresh();
        $this->assertEquals($initialBalance + 100, $this->user->balance);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'amount' => 100,
            'trx_type' => '+',
        ]);
    }

    /** @test */
    public function it_subtracts_balance_from_user()
    {
        $this->user->update(['balance' => 200]);
        $initialBalance = $this->user->balance;

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.add.sub.balance', $this->user->id), [
                'amount' => 50,
                'act' => 'sub',
                'remark' => 'Test balance subtraction',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('notify');

        $this->user->refresh();
        $this->assertEquals($initialBalance - 50, $this->user->balance);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'amount' => 50,
            'trx_type' => '-',
        ]);
    }

    /** @test */
    public function it_prevents_balance_subtraction_when_insufficient_funds()
    {
        $this->user->update(['balance' => 50]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.add.sub.balance', $this->user->id), [
                'amount' => 100,
                'act' => 'sub',
                'remark' => 'Test insufficient funds',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('notify');

        $this->user->refresh();
        $this->assertEquals(50, $this->user->balance); // Balance unchanged
    }

    /** @test */
    public function it_toggles_user_status()
    {
        // 测试禁用用户
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.status', $this->user->id), [
                'reason' => 'Test ban',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('notify');

        $this->user->refresh();
        $this->assertEquals(Status::USER_BAN, $this->user->status);
        $this->assertNotNull($this->user->ban_reason);

        // 测试启用用户
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.status', $this->user->id));

        $response->assertRedirect();
        $response->assertSessionHas('notify');

        $this->user->refresh();
        $this->assertEquals(Status::USER_ACTIVE, $this->user->status);
        $this->assertNull($this->user->ban_reason);
    }

    /** @test */
    public function it_displays_single_notification_form()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.notification.single', $this->user->id));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.notification_single');
        $response->assertViewHas('pageTitle');
        $response->assertViewHas('user');
    }

    /** @test */
    public function it_sends_single_notification()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.notification.single.send', $this->user->id), [
                'message' => 'Test notification',
                'via' => 'email',
                'subject' => 'Test Subject',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('notify');
    }

    /** @test */
    public function it_displays_bulk_notification_form()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.notification.all'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.notification_all');
        $response->assertViewHas('pageTitle');
        $response->assertViewHas('users');
    }

    /** @test */
    public function it_returns_json_user_list()
    {
        User::factory()->count(5)->create();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.list'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'users',
            'more',
        ]);
        $this->assertIsBool($response->json('success'));
        $this->assertIsBool($response->json('more'));
    }

    /** @test */
    public function it_displays_notification_log()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.notification.log', $this->user->id));

        $response->assertStatus(200);
        $response->assertViewIs('admin.reports.notification_history');
        $response->assertViewHas('pageTitle');
        $response->assertViewHas('user');
        $response->assertViewHas('logs');
    }

    /** @test */
    public function it_displays_user_referral_tree()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.tree', $this->user->username));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.tree');
        $response->assertViewHas('pageTitle');
        $response->assertViewHas('tree');
    }

    /** @test */
    public function it_displays_user_referral_list()
    {
        // 创建推荐用户
        $referredUser = User::factory()->create([
            'ref_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.ref', $this->user->id));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.list');
        $response->assertViewHas('pageTitle');
        $response->assertViewHas('users');
    }

    /** @test */
    public function it_handles_nonexistent_user()
    {
        $nonExistentId = 9999;

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.detail', $nonExistentId));

        $response->assertStatus(404);
    }

    /** @test */
    public function it_validates_user_data_pagination()
    {
        User::factory()->count(25)->create();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.all'));

        $response->assertStatus(200);
        $response->assertViewHas('users');
        $users = $response->viewData('users');
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $users);
    }

    /** @test */
    public function it_filters_users_by_search_term()
    {
        $user1 = User::factory()->create([
            'username' => 'johndoe',
            'email' => 'john@example.com',
        ]);

        $user2 = User::factory()->create([
            'username' => 'janedoe',
            'email' => 'jane@example.com',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.list') . '?search=john');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'users',
            'more',
        ]);
    }

    /** @test */
    public function it_updates_matching_bonus_settings()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.matching.update'), [
                'bv_price' => 0.5,
                'total_bv' => 1000,
                'max_bv' => 100,
                'cary_flash' => 1,
                'matching_bonus_time' => 'daily',
                'daily_time' => '12:00',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('notify');
    }
}

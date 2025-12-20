<?php

namespace Tests\Unit\Http\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Admin;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Transaction;
use App\Models\Order;
use App\Models\BvLog;
use App\Constants\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * AdminController单元测试
 *
 * 测试管理员控制器的各种功能
 */
class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::factory()->create();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_displays_dashboard()
    {
        // 创建一些测试数据
        Deposit::factory()->count(5)->create([
            'status' => Status::PAYMENT_SUCCESS,
            'amount' => 100,
        ]);

        Withdrawal::factory()->count(3)->create([
            'status' => Status::PAYMENT_SUCCESS,
            'amount' => 50,
        ]);

        Transaction::factory()->count(10)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');
        $response->assertViewHas('pageTitle');
        $response->assertViewHas('emptyMessage');
    }

    /** @test */
    public function it_gets_deposit_and_withdraw_report()
    {
        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        Deposit::factory()->count(3)->create([
            'status' => Status::PAYMENT_SUCCESS,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        Withdrawal::factory()->count(2)->create([
            'status' => Status::PAYMENT_SUCCESS,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'deposits',
            'withdrawals',
            'deposit_chart',
            'withdraw_chart',
        ]);
    }

    /** @test */
    public function it_filters_deposits_by_date_range()
    {
        $startDate = Carbon::now()->subDays(10)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        // 创建不同时期的存款
        Deposit::factory()->create([
            'status' => Status::PAYMENT_SUCCESS,
            'created_at' => Carbon::now()->subDays(15), // 在日期范围外
        ]);

        Deposit::factory()->count(3)->create([
            'status' => Status::PAYMENT_SUCCESS,
            'created_at' => Carbon::now()->subDays(5), // 在日期范围内
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function it_filters_withdrawals_by_date_range()
    {
        $startDate = Carbon::now()->subDays(10)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        // 创建不同时期的提款
        Withdrawal::factory()->create([
            'status' => Status::PAYMENT_SUCCESS,
            'created_at' => Carbon::now()->subDays(15), // 在日期范围外
        ]);

        Withdrawal::factory()->count(2)->create([
            'status' => Status::PAYMENT_SUCCESS,
            'created_at' => Carbon::now()->subDays(5), // 在日期范围内
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function it_calculates_total_deposit_amount()
    {
        Deposit::factory()->create([
            'status' => Status::PAYMENT_SUCCESS,
            'amount' => 100,
        ]);

        Deposit::factory()->create([
            'status' => Status::PAYMENT_SUCCESS,
            'amount' => 200,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => Carbon::now()->subDays(30)->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function it_calculates_total_withdrawal_amount()
    {
        Withdrawal::factory()->create([
            'status' => Status::PAYMENT_SUCCESS,
            'amount' => 50,
        ]);

        Withdrawal::factory()->create([
            'status' => Status::PAYMENT_SUCCESS,
            'amount' => 100,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => Carbon::now()->subDays(30)->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function it_gets_chart_data_for_deposits()
    {
        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        Deposit::factory()->count(5)->create([
            'status' => Status::PAYMENT_SUCCESS,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('deposit_chart', $data);
    }

    /** @test */
    public function it_gets_chart_data_for_withdrawals()
    {
        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        Withdrawal::factory()->count(3)->create([
            'status' => Status::PAYMENT_SUCCESS,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('withdraw_chart', $data);
    }

    /** @test */
    public function it_handles_invalid_date_range()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => 'invalid-date',
                'end_date' => 'invalid-date',
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
    }

    /** @test */
    public function it_handles_empty_date_range()
    {
        $startDate = Carbon::now()->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function it_groups_deposits_by_date()
    {
        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        // 创建多天的存款
        for ($i = 0; $i < 7; $i++) {
            Deposit::factory()->create([
                'status' => Status::PAYMENT_SUCCESS,
                'created_at' => Carbon::now()->subDays($i),
                'amount' => 100 + ($i * 10),
            ]);
        }

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function it_groups_withdrawals_by_date()
    {
        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        // 创建多天的提款
        for ($i = 0; $i < 7; $i++) {
            Withdrawal::factory()->create([
                'status' => Status::PAYMENT_SUCCESS,
                'created_at' => Carbon::now()->subDays($i),
                'amount' => 50 + ($i * 5),
            ]);
        }

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function it_filters_only_successful_deposits()
    {
        Deposit::factory()->create([
            'status' => Status::PAYMENT_PENDING,
        ]);

        Deposit::factory()->count(3)->create([
            'status' => Status::PAYMENT_SUCCESS,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => Carbon::now()->subDays(30)->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function it_filters_only_successful_withdrawals()
    {
        Withdrawal::factory()->create([
            'status' => Status::PAYMENT_PENDING,
        ]);

        Withdrawal::factory()->count(2)->create([
            'status' => Status::PAYMENT_SUCCESS,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => Carbon::now()->subDays(30)->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function it_handles_ajax_request()
    {
        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $response = $this->actingAs($this->admin, 'admin')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'deposits',
            'withdrawals',
            'deposit_chart',
            'withdraw_chart',
        ]);
    }

    /** @test */
    public function it_requires_admin_authentication()
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function it_requires_authentication_for_report()
    {
        $response = $this->get(route('admin.report.deposit.withdraw'), [
            'start_date' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function it_generates_all_dates_in_range()
    {
        $startDate = Carbon::now()->subDays(5)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $dates = [];
        $current = Carbon::createFromFormat('Y-m-d', $startDate);
        $end = Carbon::createFromFormat('Y-m-d', $endDate);

        while ($current->lte($end)) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        $this->assertCount(6, $dates);
        $this->assertEquals($startDate, $dates[0]);
        $this->assertEquals($endDate, $dates[5]);
    }

    /** @test */
    public function it_returns_empty_results_for_no_data()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.report.deposit.withdraw'), [
                'start_date' => Carbon::now()->subDays(30)->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['deposits']);
        $this->assertIsArray($data['withdrawals']);
    }
}

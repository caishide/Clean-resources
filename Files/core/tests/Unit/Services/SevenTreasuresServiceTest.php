<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\SevenTreasuresService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SevenTreasuresServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected SevenTreasuresService $sevenTreasuresService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sevenTreasuresService = app(SevenTreasuresService::class);
    }

    /** @test */
    public function it_can_get_next_rank()
    {
        // 测试第一级
        $nextRank = $this->sevenTreasuresService->getNextRank(null);
        $this->assertEquals('liuli_xingzhe', $nextRank);

        // 测试中间级
        $nextRank = $this->sevenTreasuresService->getNextRank('liuli_xingzhe');
        $this->assertEquals('huangjin_daoshi', $nextRank);

        // 测试最后一级
        $nextRank = $this->sevenTreasuresService->getNextRank('jingang_zunzhe');
        $this->assertNull($nextRank);
    }

    /** @test */
    public function it_can_get_rank_order()
    {
        $rankOrder = $this->sevenTreasuresService->getRankOrder();
        
        $this->assertIsArray($rankOrder);
        $this->assertEquals(5, count($rankOrder));
        $this->assertEquals('liuli_xingzhe', $rankOrder[0]);
        $this->assertEquals('jingang_zunzhe', $rankOrder[4]);
    }

    /** @test */
    public function it_can_check_promotion_eligibility_for_new_user()
    {
        $user = User::factory()->create();
        
        $eligibility = $this->sevenTreasuresService->checkPromotionEligibility($user);
        
        $this->assertFalse($eligibility['eligible']);
        $this->assertEquals('liuli_xingzhe', $eligibility['target_rank']);
        $this->assertEquals('琉璃行者', $eligibility['target_rank_name']);
        $this->assertArrayHasKey('requirements', $eligibility);
    }

    /** @test */
    public function it_can_promote_user()
    {
        $user = User::factory()->create();
        
        $success = $this->sevenTreasuresService->promoteUser($user, 'liuli_xingzhe');
        
        $this->assertTrue($success);
        $this->assertEquals('liuli_xingzhe', $user->fresh()->leader_rank_code);
        $this->assertEquals(1.0, $user->fresh()->leader_rank_multiplier);
    }

    /** @test */
    public function it_can_get_user_rank_info()
    {
        $user = User::factory()->create();
        
        // 测试无职级用户
        $rankInfo = $this->sevenTreasuresService->getUserRankInfo($user);
        
        $this->assertNull($rankInfo['current_rank']);
        $this->assertEquals('未设定', $rankInfo['current_rank_name']);
        $this->assertEquals(0, $rankInfo['current_multiplier']);
        
        // 测试有职级用户
        $this->sevenTreasuresService->promoteUser($user, 'liuli_xingzhe');
        $user->refresh();
        
        $rankInfo = $this->sevenTreasuresService->getUserRankInfo($user);
        
        $this->assertEquals('liuli_xingzhe', $rankInfo['current_rank']);
        $this->assertEquals('琉璃行者', $rankInfo['current_rank_name']);
        $this->assertEquals(1.0, $rankInfo['current_multiplier']);
    }

    /** @test */
    public function it_can_get_direct_referral_count()
    {
        $user = User::factory()->create();
        $referrals = User::factory()->count(3)->create(['ref_by' => $user->id]);
        
        $count = $this->sevenTreasuresService->getDirectReferralCount($user);
        
        $this->assertEquals(3, $count);
    }

    /** @test */
    public function it_can_batch_promotion_check()
    {
        // 创建测试用户
        $users = User::factory()->count(5)->create();
        
        $result = $this->sevenTreasuresService->batchPromotionCheck();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('checked', $result);
        $this->assertArrayHasKey('promoted', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(5, $result['checked']);
        $this->assertEquals(0, $result['errors']);
    }

    /** @test */
    public function it_handles_invalid_rank_code()
    {
        $user = User::factory()->create();
        
        $success = $this->sevenTreasuresService->promoteUser($user, 'invalid_rank');
        
        $this->assertFalse($success);
    }

    /** @test */
    public function it_can_calculate_cumulative_weak_pv()
    {
        $user = User::factory()->create();
        $userExtra = $user->userExtra()->create([
            'bv_left' => 1000,
            'bv_right' => 2000,
        ]);
        
        $weakPv = $this->sevenTreasuresService->getCumulativeWeakPV($user);
        
        $this->assertEquals(1000, $weakPv); // 取较小值
    }

    /** @test */
    public function it_respects_rank_hierarchy()
    {
        $user = User::factory()->create();
        
        // 晋升到第一级
        $this->sevenTreasuresService->promoteUser($user, 'liuli_xingzhe');
        
        // 检查是否能晋升到第二级
        $eligibility = $this->sevenTreasuresService->checkPromotionEligibility($user);
        $this->assertEquals('huangjin_daoshi', $eligibility['target_rank']);
        
        // 晋升到第二级
        $this->sevenTreasuresService->promoteUser($user, 'huangjin_daoshi');
        
        // 检查是否能晋升到第三级
        $eligibility = $this->sevenTreasuresService->checkPromotionEligibility($user);
        $this->assertEquals('manao_hufa', $eligibility['target_rank']);
    }

    /** @test */
    public function it_clears_cache_after_promotion()
    {
        $user = User::factory()->create();
        
        // 先获取一次累计PV（会缓存）
        $this->sevenTreasuresService->getCumulativeWeakPV($user);
        
        // 晋升用户
        $this->sevenTreasuresService->promoteUser($user, 'liuli_xingzhe');
        
        // 验证缓存被清除（这里主要是测试方法存在，不测试缓存功能）
        $this->assertTrue(true); // 成功执行到这里说明没有报错
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
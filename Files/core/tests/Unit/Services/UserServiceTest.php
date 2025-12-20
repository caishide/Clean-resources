<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = new UserService();
    }

    /** @test */
    public function it_can_create_a_user()
    {
        $referrer = User::factory()->create([
            'username' => 'referrer',
        ]);

        $userData = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
        ];

        $position = [
            'pos_id' => $referrer->id,
            'position' => 1,
        ];

        $user = $this->userService->createUser($userData, $position);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John', $user->firstname);
        $this->assertEquals('Doe', $user->lastname);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
        $this->assertEquals($referrer->id, $user->ref_by);
    }

    /** @test */
    public function it_can_update_user_profile()
    {
        $user = User::factory()->create([
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);

        $updateData = [
            'firstname' => 'Jane',
            'lastname' => 'Smith',
            'address' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'zip' => '10001',
        ];

        $updatedUser = $this->userService->updateProfile($user, $updateData);

        $this->assertEquals('Jane', $updatedUser->firstname);
        $this->assertEquals('Smith', $updatedUser->lastname);
        $this->assertEquals('123 Main St', $updatedUser->address);
        $this->assertEquals('New York', $updatedUser->city);
    }

    /** @test */
    public function it_can_get_user_statistics()
    {
        User::factory()->count(5)->create();

        $stats = $this->userService->getUserStatistics();

        // Check that the stats object has the expected attributes
        $this->assertNotNull($stats->total_users);
        $this->assertNotNull($stats->verified_users);
        $this->assertNotNull($stats->email_unverified_users);
        $this->assertEquals(5, $stats->total_users);
    }

    /** @test */
    public function it_can_change_user_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);

        $this->userService->changePassword($user, 'newpassword123!');

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123!', $user->password));
    }

    /** @test */
    public function it_validates_xss_in_profile_update()
    {
        $user = User::factory()->create([
            'firstname' => 'John',
        ]);

        $maliciousData = [
            'firstname' => '<script>alert("xss")</script>John',
        ];

        $updatedUser = $this->userService->updateProfile($user, $maliciousData);

        $this->assertEquals('John', $updatedUser->firstname);
        $this->assertFalse(str_contains($updatedUser->firstname, '<script>'));
    }

    /** @test */
    public function it_can_ban_user()
    {
        $user = User::factory()->create([
            'status' => 1,
        ]);

        $this->userService->banUser($user, 'Test ban reason');

        $user->refresh();
        $this->assertEquals(0, $user->status);
        $this->assertEquals('Test ban reason', $user->ban_reason);
    }

    /** @test */
    public function it_can_unban_user()
    {
        $user = User::factory()->create([
            'status' => 0,
            'ban_reason' => 'Previous ban',
        ]);

        $this->userService->unbanUser($user);

        $user->refresh();
        $this->assertEquals(1, $user->status);
        $this->assertNull($user->ban_reason);
    }

    /** @test */
    public function it_can_verify_email()
    {
        $user = User::factory()->create([
            'ev' => 0,
        ]);

        $this->userService->verifyEmail($user);

        $user->refresh();
        $this->assertEquals(1, $user->ev);
    }

    /** @test */
    public function it_can_verify_phone()
    {
        $user = User::factory()->create([
            'sv' => 0,
        ]);

        $this->userService->verifyPhone($user);

        $user->refresh();
        $this->assertEquals(1, $user->sv);
    }

    /** @test */
    public function it_can_get_user_by_username()
    {
        $user = User::factory()->create([
            'username' => 'testuser',
        ]);

        $foundUser = $this->userService->getUserByUsername('testuser');

        $this->assertInstanceOf(User::class, $foundUser);
        $this->assertEquals($user->id, $foundUser->id);
    }

    /** @test */
    public function it_can_get_user_by_email()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $foundUser = $this->userService->getUserByEmail('test@example.com');

        $this->assertInstanceOf(User::class, $foundUser);
        $this->assertEquals($user->id, $foundUser->id);
    }

    /** @test */
    public function it_can_search_users()
    {
        User::factory()->create([
            'username' => 'johndoe',
            'email' => 'john@example.com',
        ]);

        User::factory()->create([
            'username' => 'janedoe',
            'email' => 'jane@example.com',
        ]);

        $results = $this->userService->searchUsers('john');

        $this->assertCount(1, $results);
        $this->assertEquals('johndoe', $results->first()->username);
    }

    /** @test */
    public function it_can_get_user_count_by_status()
    {
        User::factory()->count(3)->create([
            'status' => 1,
        ]);

        User::factory()->count(2)->create([
            'status' => 0,
        ]);

        $activeCount = $this->userService->getUserCountByStatus(1);
        $bannedCount = $this->userService->getUserCountByStatus(0);

        $this->assertEquals(3, $activeCount);
        $this->assertEquals(2, $bannedCount);
    }

    /** @test */
    public function it_can_get_recent_users()
    {
        User::factory()->count(10)->create();

        $recentUsers = $this->userService->getRecentUsers(5);

        $this->assertCount(5, $recentUsers);
    }

    /** @test */
    public function it_can_get_users_by_referrer()
    {
        $referrer = User::factory()->create([
            'username' => 'referrer',
        ]);

        User::factory()->count(3)->create([
            'ref_by' => $referrer->id,
        ]);

        User::factory()->count(2)->create([
            'ref_by' => 999, // 不同推荐人
        ]);

        $referredUsers = $this->userService->getUsersByReferrer($referrer->id);

        $this->assertCount(3, $referredUsers);
    }

    /** @test */
    public function it_can_update_user_balance()
    {
        $user = User::factory()->create([
            'balance' => 100,
        ]);

        $this->userService->updateBalance($user, 50);

        $user->refresh();
        $this->assertEquals(150, $user->balance);
    }

    /** @test */
    public function it_can_deduct_user_balance()
    {
        $user = User::factory()->create([
            'balance' => 100,
        ]);

        $this->userService->deductBalance($user, 30);

        $user->refresh();
        $this->assertEquals(70, $user->balance);
    }

    /** @test */
    public function it_prevents_negative_balance()
    {
        $user = User::factory()->create([
            'balance' => 50,
        ]);

        $this->userService->deductBalance($user, 100);

        $user->refresh();
        $this->assertEquals(50, $user->balance); // 余额不应变为负数
    }
}

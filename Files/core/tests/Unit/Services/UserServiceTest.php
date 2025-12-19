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

        $this->assertObjectHasAttribute('total_users', $stats);
        $this->assertObjectHasAttribute('verified_users', $stats);
        $this->assertObjectHasAttribute('active_users', $stats);
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
        $this->assertNotContains('<script>', $updatedUser->firstname);
    }
}

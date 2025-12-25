<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserExtra;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

class UserTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function user_can_be_created()
    {
        $user = User::factory()->create([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);
    }

    /** @test */
    public function user_password_is_hashed()
    {
        $password = 'password123';
        $user = User::factory()->create([
            'password' => $password,
        ]);

        $this->assertNotEquals($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));
    }

    /** @test */
    public function user_can_have_user_extra()
    {
        $user = User::factory()->create();
        UserExtra::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(UserExtra::class, $user->userExtra);
    }

    /** @test */
    public function user_can_check_if_owns_resource()
    {
        $user = User::factory()->create();
        $resource = new class {
            public $user_id;
        };
        $resource->user_id = $user->id;

        $this->assertTrue($user->owns($resource));
    }

    /** @test */
    public function user_can_check_if_does_not_own_resource()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $resource = new class {
            public $user_id;
        };
        $resource->user_id = $otherUser->id;

        $this->assertFalse($user->owns($resource));
    }

    /** @test */
    public function user_scope_filters_active_users()
    {
        $activeUser = User::factory()->create(['status' => 1]);
        $inactiveUser = User::factory()->create(['status' => 0]);

        $activeUsers = User::active()->get();

        $this->assertTrue($activeUsers->contains($activeUser));
        $this->assertFalse($activeUsers->contains($inactiveUser));
    }

    /** @test */
    public function user_scope_filters_verified_users()
    {
        $verifiedUser = User::factory()->create(['ev' => 1]);
        $unverifiedUser = User::factory()->create(['ev' => 0]);

        $verifiedUsers = User::emailVerified()->get();

        $this->assertTrue($verifiedUsers->contains($verifiedUser));
        $this->assertFalse($verifiedUsers->contains($unverifiedUser));
    }

    /** @test */
    public function user_can_get_full_name()
    {
        $user = User::factory()->create([
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);

        $this->assertEquals('John Doe', $user->full_name);
    }

    /** @test */
    public function user_has_correct_fillable_attributes()
    {
        $user = new User();
        $fillable = $user->getFillable();

        $this->assertContains('firstname', $fillable);
        $this->assertContains('lastname', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('password', $fillable);
        $this->assertContains('ref_by', $fillable);
    }
}

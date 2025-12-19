<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IDORSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $user1;
    protected $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::create([
            'firstname' => 'User',
            'lastname' => 'One',
            'username' => 'user1',
            'email' => 'user1@test.com',
            'password' => bcrypt('password'),
            'status' => Status::USER_ACTIVE,
            'ev' => Status::VERIFIED,
            'sv' => Status::VERIFIED,
        ]);

        $this->user2 = User::create([
            'firstname' => 'User',
            'lastname' => 'Two',
            'username' => 'user2',
            'email' => 'user2@test.com',
            'password' => bcrypt('password'),
            'status' => Status::USER_ACTIVE,
            'ev' => Status::VERIFIED,
            'sv' => Status::VERIFIED,
        ]);
    }

    /** @test */
    public function user_cannot_access_another_user_referral_link()
    {
        $response = $this->actingAs($this->user1)
            ->get(route('home') . '?reference=' . $this->user2->username);

        $response->assertStatus(200);
        // Should not change user's reference to another user
        $this->assertNotEquals(session('reference'), $this->user2->username);
    }

    /** @test */
    public function user_cannot_access_another_user_download()
    {
        // User1 creates a file
        $fileHash = encrypt('attachments/user1-file.pdf');

        // User2 tries to access User1's file
        $response = $this->actingAs($this->user2)
            ->get(route('user.download.attachment', $fileHash));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function user_cannot_transfer_to_another_user_without_validation()
    {
        $this->user1->update([
            'balance' => 1000,
            'balance_convert' => 1000,
        ]);

        $response = $this->actingAs($this->user1)
            ->post(route('user.transfer'), [
                'username' => $this->user2->username,
                'amount' => 100,
            ]);

        $response->assertStatus(200);
        $response->assertViewIs('user.transfer.check');
    }

    /** @test */
    public function user_cannot_transfer_without_correct_pin()
    {
        $this->user1->update([
            'balance' => 1000,
            'balance_convert' => 1000,
        ]);

        $response = $this->actingAs($this->user1)
            ->post(route('user.transfer'), [
                'username' => $this->user2->username,
                'amount' => 100,
            ]);

        $response->assertStatus(200);
        $response->assertViewIs('user.transfer.check');

        // Try to confirm transfer without PIN
        $response = $this->actingAs($this->user1)
            ->post(route('user.transfer.confirm'), [
                'pin' => 'wrong_pin',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function invalid_reference_parameter_is_rejected()
    {
        $response = $this->actingAs($this->user1)
            ->get(route('home') . '?reference=<script>alert(1)</script>');

        $response->assertStatus(200);
        // Should not accept malicious input
        $this->assertNotEquals(session('reference'), '<script>alert(1)</script>');
    }

    /** @test */
    public function overly_long_reference_parameter_is_rejected()
    {
        $longReference = str_repeat('a', 100);

        $response = $this->actingAs($this->user1)
            ->get(route('home') . '?reference=' . $longReference);

        $response->assertStatus(200);
        // Should reject overly long parameter
        $this->assertNotEquals(session('reference'), $longReference);
    }

    /** @test */
    public function sql_injection_in_reference_is_prevented()
    {
        $maliciousReference = "'; DROP TABLE users; --";

        $response = $this->actingAs($this->user1)
            ->get(route('home') . '?reference=' . $maliciousReference);

        $response->assertStatus(200);
        // Should not execute malicious SQL
        $this->assertDatabaseHas('users', ['id' => $this->user1->id]);
    }
}

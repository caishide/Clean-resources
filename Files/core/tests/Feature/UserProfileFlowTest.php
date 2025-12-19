<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class UserProfileFlowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function user_can_view_profile_settings_page()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/profile-setting')
            ->assertStatus(200);

        $response->assertViewIs('Template::user.profile_setting');
        $response->assertViewHas('user');
        $response->assertViewHas('pageTitle', 'Profile Setting');
    }

    /** @test */
    public function user_can_update_profile_with_valid_data()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/profile-setting', [
            'firstname' => 'Jane',
            'lastname' => 'Smith',
            'address' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'zip' => '10001',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('notify');

        $user->refresh();
        $this->assertEquals('Jane', $user->firstname);
        $this->assertEquals('Smith', $user->lastname);
        $this->assertEquals('123 Main St', $user->address);
    }

    /** @test */
    public function user_cannot_update_profile_with_xss_attempts()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/profile-setting', [
            'firstname' => '<script>alert("xss")</script>John',
            'lastname' => '<img src=x onerror=alert("xss")>Doe',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('notify');

        $user->refresh();
        $this->assertEquals('John', $user->firstname);
        $this->assertEquals('Doe', $user->lastname);
        $this->assertNotContains('<script>', $user->firstname);
        $this->assertNotContains('<img', $user->lastname);
    }

    /** @test */
    public function user_can_change_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('oldpassword'),
        ]);

        $response = $this->actingAs($user)->post('/change-password', [
            'current_password' => 'oldpassword',
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('notify');

        $user->refresh();
        $this->assertTrue(\Hash::check('NewSecurePass123!', $user->password));
    }

    /** @test */
    public function user_cannot_change_password_with_wrong_current_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('correctpassword'),
        ]);

        $response = $this->actingAs($user)->post('/change-password', [
            'current_password' => 'wrongpassword',
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();

        $user->refresh();
        $this->assertTrue(\Hash::check('correctpassword', $user->password));
    }

    /** @test */
    public function user_cannot_change_password_with_weak_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('currentpassword'),
        ]);

        $response = $this->actingAs($user)->post('/change-password', [
            'current_password' => 'currentpassword',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function user_cannot_change_password_with_compromised_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('currentpassword'),
        ]);

        $response = $this->actingAs($user)->post('/change-password', [
            'current_password' => 'currentpassword',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('password');
        $this->assertStringContainsString('compromised', session('errors')->first('password'));
    }

    /** @test */
    public function user_cannot_update_profile_without_authentication()
    {
        $response = $this->post('/profile-setting', [
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);

        $response->assertRedirect('/login');
    }

    /** @test */
    public function user_cannot_change_password_without_authentication()
    {
        $response = $this->post('/change-password', [
            'current_password' => 'oldpassword',
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ]);

        $response->assertRedirect('/login');
    }

    /** @test */
    public function user_can_upload_profile_image()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/profile-setting', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'image' => \Illuminate\Http\UploadedFile::fake()->image('avatar.jpg', 100, 100),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('notify');

        $user->refresh();
        $this->assertNotNull($user->image);
    }

    /** @test */
    public function user_cannot_upload_malicious_file_type()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/profile-setting', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'image' => \Illuminate\Http\UploadedFile::fake()->create('malicious.php', 100),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('image');
    }
}

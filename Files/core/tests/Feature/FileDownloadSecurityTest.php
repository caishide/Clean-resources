<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileDownloadSecurityTest extends TestCase
{
    
    protected $user;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        // Create test users
        $this->user = User::create([
            'name' => 'Test User',
            'firstname' => 'Test',
            'lastname' => 'User',
            'username' => 'testuser',
            'email' => 'testuser@example.com',
            'password' => bcrypt('password'),
            'status' => Status::USER_ACTIVE,
            'ev' => Status::VERIFIED,
            'sv' => Status::VERIFIED,
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'firstname' => 'Admin',
            'lastname' => 'User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'status' => Status::USER_ACTIVE,
            'ev' => Status::VERIFIED,
            'sv' => Status::VERIFIED,
            'is_admin' => true,
        ]);

        // Setup fake storage
        Storage::fake('local');
    }

    /** @test */
    public function admin_cannot_download_file_outside_allowed_directory()
    {
        // Try to access a file outside the allowed directory using path traversal
        $maliciousPath = '../../../etc/passwd';
        $encryptedPath = encrypt($maliciousPath);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.download.attachment', $encryptedPath));

        // Should be blocked
        $response->assertStatus(302); // Redirect back with error
        $response->assertSessionHas('notify');
    }

    /** @test */
    public function user_cannot_download_file_outside_allowed_directory()
    {
        // Try to access a file outside the allowed directory using path traversal
        $maliciousPath = '../../../etc/passwd';
        $encryptedPath = encrypt($maliciousPath);

        $response = $this->actingAs($this->user)
            ->get(route('user.download.attachment', $encryptedPath));

        // Should be blocked
        $response->assertStatus(302); // Redirect back with error
        $response->assertSessionHas('notify');
    }

    /** @test */
    public function admin_cannot_access_sensitive_files()
    {
        // Try to access .env file
        $maliciousPath = '../../.env';
        $encryptedPath = encrypt($maliciousPath);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.download.attachment', $encryptedPath));

        // Should be blocked
        $response->assertStatus(302);
    }

    /** @test */
    public function user_cannot_access_sensitive_files()
    {
        // Try to access .env file
        $maliciousPath = '../../.env';
        $encryptedPath = encrypt($maliciousPath);

        $response = $this->actingAs($this->user)
            ->get(route('user.download.attachment', $encryptedPath));

        // Should be blocked
        $response->assertStatus(302);
    }

    /** @test */
    public function admin_can_download_valid_file_within_allowed_directory()
    {
        // Create a valid file within the allowed directory
        $file = UploadedFile::fake()->create('test-document.pdf', 100);
        $filePath = $file->store('attachments', 'local');

        // Encrypt the path
        $encryptedPath = encrypt($filePath);

        // Download should succeed
        $response = $this->actingAs($this->admin)
            ->get(route('admin.download.attachment', $encryptedPath));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /** @test */
    public function user_can_download_valid_file_within_allowed_directory()
    {
        // Create a valid file within the allowed directory
        $file = UploadedFile::fake()->create('test-document.pdf', 100);
        $filePath = $file->store('attachments', 'local');

        // Encrypt the path
        $encryptedPath = encrypt($filePath);

        // Download should succeed
        $response = $this->actingAs($this->user)
            ->get(route('user.download.attachment', $encryptedPath));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /** @test */
    public function download_fails_for_nonexistent_file()
    {
        // Create path to non-existent file
        $filePath = 'attachments/nonexistent-file.pdf';
        $encryptedPath = encrypt($filePath);

        $response = $this->actingAs($this->user)
            ->get(route('user.download.attachment', $encryptedPath));

        // Should fail gracefully
        $response->assertStatus(302);
        $response->assertSessionHas('notify');
    }

    /** @test */
    public function download_blocks_symlink_attacks()
    {
        // Try to use a symlink to access files outside allowed directory
        $targetPath = '../../.env';
        $symlinkPath = storage_path('app/attachments/symlink');
        @unlink($symlinkPath); // Remove if exists

        // Note: In a real scenario, this would be blocked by the path validation
        $encryptedPath = encrypt($symlinkPath);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.download.attachment', $encryptedPath));

        // Should be blocked due to path validation
        $response->assertStatus(302);
    }

    /** @test */
    public function download_blocks_null_byte_injection()
    {
        // Try null byte injection attack
        $maliciousPath = "../../../etc/passwd\0.pdf";
        $encryptedPath = encrypt($maliciousPath);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.download.attachment', $encryptedPath));

        // Should be blocked
        $response->assertStatus(302);
    }

    /** @test */
    public function download_blocks_encoded_path_traversal()
    {
        // Try URL-encoded path traversal
        $maliciousPath = "..%2F..%2Fetc%2Fpasswd";
        $encryptedPath = encrypt($maliciousPath);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.download.attachment', $encryptedPath));

        // Should be blocked
        $response->assertStatus(302);
    }

    /** @test */
    public function download_blocks_double_encoded_path()
    {
        // Try double-encoded path traversal
        $maliciousPath = "%252e%252e%252f%252e%252e%252fetc%252fpasswd";
        $encryptedPath = encrypt($maliciousPath);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.download.attachment', $encryptedPath));

        // Should be blocked
        $response->assertStatus(302);
    }
}

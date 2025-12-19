<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class SendWelcomeEmailJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
    }

    /** @test */
    public function it_can_be_created_with_user()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'firstname' => 'John',
        ]);

        $job = new SendWelcomeEmailJob($user);

        $this->assertInstanceOf(User::class, $job->user);
        $this->assertEquals('john@example.com', $job->user->email);
    }

    /** @test */
    public function it_sends_welcome_email()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'firstname' => 'John',
        ]);

        $job = new SendWelcomeEmailJob($user);

        $job->handle();

        Mail::assertSent(\App\Mail\WelcomeEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /** @test */
    public function it_handles_mail_sending_failure()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'firstname' => 'John',
        ]);

        Mail::shouldReceive('to')->andThrow(new \Exception('Mail service unavailable'));

        $job = new SendWelcomeEmailJob($user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Mail service unavailable');

        $job->handle();
    }

    /** @test */
    public function it_can_be_queued()
    {
        $user = User::factory()->create();

        SendWelcomeEmailJob::dispatch($user);

        Queue::assertPushed(SendWelcomeEmailJob::class);
    }

    /** @test */
    public function it_includes_user_data_in_email()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);

        $job = new SendWelcomeEmailJob($user);

        $job->handle();

        Mail::assertSent(\App\Mail\WelcomeEmail::class, function ($mail) use ($user) {
            return $mail->user->id === $user->id &&
                   $mail->user->firstname === 'John';
        });
    }
}

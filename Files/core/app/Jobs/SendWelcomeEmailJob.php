<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * SendWelcomeEmailJob - Queue job for sending welcome emails
 *
 * This job is dispatched after successful user registration
 * to send welcome emails asynchronously, improving performance.
 */
class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Send welcome email
            Mail::to($this->user->email)
                ->send(new \App\Mail\WelcomeEmail($this->user));

            Log::channel('application')->info('Welcome email sent successfully', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
            ]);
        } catch (\Exception $e) {
            Log::channel('application')->error('Failed to send welcome email', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage(),
            ]);

            // Rethrow to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('application')->error('Welcome email job failed after retries', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'exception' => $exception->getMessage(),
        ]);

        // Could implement fallback notification here
        // e.g., notify admin via Slack
    }
}

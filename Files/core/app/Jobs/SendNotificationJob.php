<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * SendNotificationJob - Queue job for sending notifications
 *
 * Handles sending email, SMS, and push notifications
 * asynchronously to improve application performance.
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public string $type,
        public array $data = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            switch ($this->type) {
                case 'payment_success':
                    $this->sendPaymentSuccessNotification();
                    break;

                case 'withdraw_request':
                    $this->sendWithdrawRequestNotification();
                    break;

                case 'referral_bonus':
                    $this->sendReferralBonusNotification();
                    break;

                case 'binary_commission':
                    $this->sendBinaryCommissionNotification();
                    break;

                default:
                    Log::warning('Unknown notification type', [
                        'type' => $this->type,
                        'user_id' => $this->user->id,
                    ]);
            }

            Log::channel('application')->info('Notification sent successfully', [
                'user_id' => $this->user->id,
                'type' => $this->type,
                'data' => $this->data,
            ]);
        } catch (\Exception $e) {
            Log::channel('application')->error('Notification sending failed', [
                'user_id' => $this->user->id,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send payment success notification
     */
    private function sendPaymentSuccessNotification(): void
    {
        // Email notification
        if ($this->user->email_verified) {
            Notification::send($this->user, new \App\Notifications\PaymentSuccessNotification($this->data));
        }

        // SMS notification (if mobile is verified)
        if ($this->user->sms_verified && $this->user->mobile) {
            // Send SMS using your SMS service
            // Example: SMS::to($this->user->mobile)->send('Payment received...');
        }

        // Push notification (if device token exists)
        if ($this->user->device_token) {
            // Send push notification using FCM or similar
        }
    }

    /**
     * Send withdraw request notification
     */
    private function sendWithdrawRequestNotification(): void
    {
        Notification::send($this->user, new \App\Notifications\WithdrawRequestNotification($this->data));
    }

    /**
     * Send referral bonus notification
     */
    private function sendReferralBonusNotification(): void
    {
        Notification::send($this->user, new \App\Notifications\ReferralBonusNotification($this->data));
    }

    /**
     * Send binary commission notification
     */
    private function sendBinaryCommissionNotification(): void
    {
        Notification::send($this->user, new \App\Notifications\BinaryCommissionNotification($this->data));
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('application')->error('Notification job failed', [
            'user_id' => $this->user->id,
            'type' => $this->type,
            'exception' => $exception->getMessage(),
        ]);
    }
}

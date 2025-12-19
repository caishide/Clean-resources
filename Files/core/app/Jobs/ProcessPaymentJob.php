<?php

namespace App\Jobs;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * ProcessPaymentJob - Queue job for processing payments asynchronously
 *
 * Processes payment confirmations, updates user balance,
 * and creates transaction records asynchronously.
 */
class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Deposit $deposit
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            try {
                // Update deposit status
                $this->deposit->update([
                    'status' => 2, // Success status
                    'admin_feedback' => 'Payment processed successfully',
                ]);

                // Update user balance
                $user = $this->deposit->user;
                $user->increment('balance', $this->deposit->amount);

                // Create transaction record
                $user->transactions()->create([
                    'amount' => $this->deposit->amount,
                    'charge' => $this->deposit->charge,
                    'trx_type' => '+',
                    'trx' => $this->deposit->trx,
                    'details' => 'Deposit via ' . $this->deposit->gateway->name,
                    'remark' => 'deposit',
                ]);

                Log::channel('application')->info('Payment processed successfully', [
                    'deposit_id' => $this->deposit->id,
                    'user_id' => $this->deposit->user_id,
                    'amount' => $this->deposit->amount,
                    'trx' => $this->deposit->trx,
                ]);

                // Dispatch notification job
                SendNotificationJob::dispatch($this->deposit->user, 'payment_success', [
                    'amount' => $this->deposit->amount,
                    'trx' => $this->deposit->trx,
                ]);
            } catch (\Exception $e) {
                Log::channel('application')->error('Payment processing failed', [
                    'deposit_id' => $this->deposit->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('application')->error('Payment processing job failed', [
            'deposit_id' => $this->deposit->id,
            'user_id' => $this->deposit->user_id,
            'exception' => $exception->getMessage(),
        ]);

        // Mark deposit as failed
        $this->deposit->update([
            'status' => 3, // Failed status
            'admin_feedback' => 'Payment processing failed: ' . $exception->getMessage(),
        ]);
    }
}

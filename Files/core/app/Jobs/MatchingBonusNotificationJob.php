<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class MatchingBonusNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The user instance.
     *
     * @var User
     */
    protected $user;

    /**
     * Bonus amount.
     *
     * @var string
     */
    protected $amount;

    /**
     * Paid BV amount.
     *
     * @var int
     */
    protected $paidBv;

    /**
     * Post balance.
     *
     * @var string
     */
    protected $postBalance;

    /**
     * Transaction ID.
     *
     * @var string
     */
    protected $trx;

    /**
     * Create a new job instance.
     *
     * @param User $user
     * @param string $amount
     * @param int $paidBv
     * @param string $postBalance
     * @param string $trx
     * @return void
     */
    public function __construct(
        User $user,
        string $amount,
        int $paidBv,
        string $postBalance,
        string $trx
    ) {
        $this->user = $user;
        $this->amount = $amount;
        $this->paidBv = $paidBv;
        $this->postBalance = $postBalance;
        $this->trx = $trx;
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            notify($this->user, 'MATCHING_BONUS', [
                'amount' => $this->amount,
                'paid_bv' => $this->paidBv,
                'post_balance' => $this->postBalance,
                'trx' => $this->trx,
            ]);
        } catch (\Exception $e) {
            // 记录错误但不再抛出
            \Illuminate\Support\Facades\Log::channel('performance')->error('Matching bonus notification failed', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

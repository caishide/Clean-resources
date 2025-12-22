<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Exception;

class TransactionService extends BaseService
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Transfer balance between users
     */
    public function transferBalance(User $sender, User $recipient, float $amount, float $charge): array
    {
        $this->logInfo('Balance transfer initiated', [
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => $amount,
            'charge' => $charge,
        ]);

        return $this->transaction(function () use ($sender, $recipient, $amount, $charge) {
            $totalDeduction = $amount + $charge;

            // Validate sender balance
            if ($sender->balance < $totalDeduction) {
                throw new Exception('Insufficient balance');
            }

            if ($sender->balance < 0) {
                throw new Exception('Negative balance, transfer not allowed');
            }

            // Deduct from sender
            $sender->balance -= $totalDeduction;
            $sender->save();

            $trx = getTrx();

            // Record sender transaction
            $senderTrx = $this->createTransaction([
                'user_id' => $sender->id,
                'trx' => $trx,
                'trx_type' => '-',
                'remark' => 'balance_transfer',
                'details' => "Balance transferred to {$recipient->username}",
                'amount' => $amount,
                'post_balance' => $sender->balance,
                'charge' => $charge,
            ]);

            // Add to recipient
            $recipient->balance += $amount;
            $recipient->save();

            // Record recipient transaction
            $recipientTrx = $this->createTransaction([
                'user_id' => $recipient->id,
                'trx' => $trx,
                'trx_type' => '+',
                'remark' => 'balance_receive',
                'details' => "Balance received from {$sender->username}",
                'amount' => $amount,
                'post_balance' => $recipient->balance,
                'charge' => 0,
            ]);

            // Clear caches
            $this->userService->clearDashboardCache($sender->id);
            $this->userService->clearDashboardCache($recipient->id);

            $this->logInfo('Balance transfer completed', [
                'trx' => $trx,
                'sender_balance' => $sender->balance,
                'recipient_balance' => $recipient->balance,
            ]);

            return [
                'success' => true,
                'trx' => $trx,
                'sender_transaction' => $senderTrx,
                'recipient_transaction' => $recipientTrx,
            ];
        });
    }

    /**
     * Create a new transaction record
     */
    public function createTransaction(array $data): Transaction
    {
        $transaction = new Transaction();
        $transaction->fill($data);
        $transaction->save();

        return $transaction;
    }

    /**
     * Get transaction by TRX code
     */
    public function findByTrx(string $trx): ?Transaction
    {
        return Transaction::where('trx', $trx)->first();
    }

    /**
     * Calculate transfer charge
     */
    public function calculateTransferCharge(float $amount): float
    {
        $general = gs();
        return $general->bal_trans_fixed_charge + (($amount * $general->bal_trans_per_charge) / 100);
    }
}

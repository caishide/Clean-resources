<?php
namespace App\Traits;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * UserNotify - Trait for user notification and filtering
 *
 * Provides methods for filtering users by various criteria
 * and generating user notification types.
 */
trait UserNotify
{
    /**
     * Get notification types for users
     *
     * @return array<string, string>
     */
    public static function notifyToUser(): array
    {
        return [
            'allUsers'              => 'All Users',
            'selectedUsers'         => 'Selected Users',
            'kycUnverified'         => 'Kyc Unverified Users',
            'kycVerified'           => 'Kyc Verified Users',
            'kycPending'            => 'Kyc Pending Users',
            'withBalance'           => 'With Balance Users',
            'emptyBalanceUsers'     => 'Empty Balance Users',
            'twoFaDisableUsers'     => '2FA Disable User',
            'twoFaEnableUsers'      => '2FA Enable User',
            'hasDepositedUsers'       => 'Deposited Users',
            'notDepositedUsers'       => 'Not Deposited Users',
            'pendingDepositedUsers'   => 'Pending Deposited Users',
            'rejectedDepositedUsers'  => 'Rejected Deposited Users',
            'topDepositedUsers'     => 'Top Deposited Users',
            'hasWithdrawUsers'      => 'Withdraw Users',
            'pendingWithdrawUsers'  => 'Pending Withdraw Users',
            'rejectedWithdrawUsers' => 'Rejected Withdraw Users',
            'pendingTicketUser'     => 'Pending Ticket Users',
            'answerTicketUser'      => 'Answer Ticket Users',
            'closedTicketUser'      => 'Closed Ticket Users',
            'notLoginUsers'         => 'Last Few Days Not Login Users',
            'paidUser'              => 'Users who have purchased plans',
            'freeUser'              => 'Users who have not purchased plan',
        ];
    }

    /**
     * Scope to filter selected users
     */
    public function scopeSelectedUsers(Builder $query): Builder
    {
        return $query->whereIn('id', request()->user ?? []);
    }

    /**
     * Scope to include all users
     */
    public function scopeAllUsers(Builder $query): Builder
    {
        return $query;
    }

    /**
     * Scope to filter users with empty balance
     */
    public function scopeEmptyBalanceUsers(Builder $query): Builder
    {
        return $query->where('balance', '<=', 0);
    }

    /**
     * Scope to filter users with 2FA disabled
     */
    public function scopeTwoFaDisableUsers(Builder $query): Builder
    {
        return $query->where('ts', Status::DISABLE);
    }

    /**
     * Scope to filter users with 2FA enabled
     */
    public function scopeTwoFaEnableUsers(Builder $query): Builder
    {
        return $query->where('ts', Status::ENABLE);
    }

    /**
     * Scope to filter users who have made deposits
     */
    public function scopeHasDepositedUsers(Builder $query): Builder
    {
        return $query->whereHas('deposits', function ($deposit) {
            $deposit->successful();
        });
    }

    /**
     * Scope to filter users who have not made deposits
     */
    public function scopeNotDepositedUsers(Builder $query): Builder
    {
        return $query->whereDoesntHave('deposits', function ($q) {
            $q->successful();
        });
    }

    /**
     * Scope to filter users with pending deposits
     */
    public function scopePendingDepositedUsers(Builder $query): Builder
    {
        return $query->whereHas('deposits', function ($deposit) {
            $deposit->pending();
        });
    }

    /**
     * Scope to filter users with rejected deposits
     */
    public function scopeRejectedDepositedUsers(Builder $query): Builder
    {
        return $query->whereHas('deposits', function ($deposit) {
            $deposit->rejected();
        });
    }

    /**
     * Scope to filter top deposited users
     */
    public function scopeTopDepositedUsers(Builder $query): Builder
    {
        return $query->whereHas('deposits', function ($deposit) {
            $deposit->successful();
        })->withSum(['deposits' => function ($q) {
            $q->successful();
        }], 'amount')->orderBy('deposits_sum_amount', 'desc')->take(request()->number_of_top_deposited_user ?? 10);
    }

    /**
     * Scope to filter users who have made withdrawals
     */
    public function scopeHasWithdrawUsers(Builder $query): Builder
    {
        return $query->whereHas('withdrawals', function ($q) {
            $q->approved();
        });
    }

    /**
     * Scope to filter users with pending withdrawals
     */
    public function scopePendingWithdrawUsers(Builder $query): Builder
    {
        return $query->whereHas('withdrawals', function ($q) {
            $q->pending();
        });
    }

    /**
     * Scope to filter users with rejected withdrawals
     */
    public function scopeRejectedWithdrawUsers(Builder $query): Builder
    {
        return $query->whereHas('withdrawals', function ($q) {
            $q->rejected();
        });
    }

    /**
     * Scope to filter users with pending tickets
     */
    public function scopePendingTicketUser(Builder $query): Builder
    {
        return $query->whereHas('tickets', function ($q) {
            $q->whereIn('status', [Status::TICKET_OPEN, Status::TICKET_REPLY]);
        });
    }

    /**
     * Scope to filter users with closed tickets
     */
    public function scopeClosedTicketUser(Builder $query): Builder
    {
        return $query->whereHas('tickets', function ($q) {
            $q->where('status', Status::TICKET_CLOSE);
        });
    }

    /**
     * Scope to filter users with answered tickets
     */
    public function scopeAnswerTicketUser(Builder $query): Builder
    {
        return $query->whereHas('tickets', function ($q) {
            $q->where('status', Status::TICKET_ANSWER);
        });
    }

    /**
     * Scope to filter users who have not logged in recently
     */
    public function scopeNotLoginUsers(Builder $query): Builder
    {
        return $query->whereDoesntHave('loginLogs', function ($q) {
            $q->whereDate('created_at', '>=', now()->subDays(request()->number_of_days ?? 10));
        });
    }

    /**
     * Scope to filter KYC verified users
     */
    public function scopeKycVerified(Builder $query): Builder
    {
        return $query->where('kv', Status::KYC_VERIFIED);
    }

    /**
     * Scope to filter paid users (users with purchased plans)
     */
    public function scopePaidUser(Builder $query): Builder
    {
        return $query->whereNotIn('plan_id', [0]);
    }

    /**
     * Scope to filter free users (users without purchased plans)
     */
    public function scopeFreeUser(Builder $query): Builder
    {
        return $query->where('plan_id', 0);
    }
}

<?php

namespace App\Models;

use App\Constants\Status;
use App\Traits\UserNotify;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, UserNotify, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'username',
        'email',
        'password',
        'phone',
        'address',
        'city',
        'state',
        'zip',
        'country_name',
        'country_code',
        'dial_code',
        'ref_by',
        'pos_id',
        'plan_id',
        'balance',
        'interest_balance',
        'total_invest',
        'total_ref',
        'total_binary_left',
        'total_binary_right',
        'daily_binary_left',
        'daily_binary_right',
        'weekly_binary_left',
        'weekly_binary_right',
        'pv',
        'status',
        'ev',
        'sv',
        'tv',
        'kv',
        'ts',
        'ver_code',
        'ver_code_send_at',
        'two_factor',
        'two_factor_secret',
        'ban_reason',
        'last_login',
        'last_active_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'ver_code', 'balance', 'kyc_data'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'kyc_data' => 'object',
        'ver_code_send_at' => 'datetime'
    ];


    /**
     * Get the user's login logs
     */
    public function loginLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserLogin::class);
    }

    /**
     * Get the user's transactions
     */
    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Transaction::class)->orderBy('id', 'desc');
    }

    /**
     * Get the user's deposits
     */
    public function deposits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Deposit::class)->where('status', '!=', Status::PAYMENT_INITIATE);
    }

    /**
     * Get the user's withdrawals
     */
    public function withdrawals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Withdrawal::class)->where('status', '!=', Status::PAYMENT_INITIATE);
    }

    /**
     * Get the user's support tickets
     */
    public function tickets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Get the user's plan
     */
    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the user who referred this user
     */
    public function refBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'ref_by');
    }

    // V10.1 口径：Sponsor（推荐人）
    /**
     * Get the sponsor of this user
     */
    public function sponsor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'ref_by');
    }

    // V10.1 口径：Placement 上级
    /**
     * Get the placement parent of this user
     */
    public function placementParent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'pos_id');
    }

    public function fullname(): Attribute
    {
        return new Attribute(
            get: fn () => $this->firstname . ' ' . $this->lastname,
        );
    }

    public function mobileNumber(): Attribute
    {
        return new Attribute(
            get: fn () => $this->dial_code . $this->mobile,
        );
    }

    /**
     * Get the user's device tokens
     */
    public function deviceTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Get the user's extra information
     */
    public function userExtra(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserExtra::class);
    }

    // SCOPES

    /**
     * Scope to filter active users (status active, email verified, sms verified)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', Status::USER_ACTIVE)->where('ev', Status::VERIFIED)->where('sv', Status::VERIFIED);
    }

    /**
     * Scope to filter paid users (users with purchased plans)
     */
    public function scopePaidUser(Builder $query): Builder
    {
        return $query->where('plan_id', '!=', 0);
    }

    /**
     * Scope to filter free users (users without purchased plans)
     */
    public function scopeFreeUser(Builder $query): Builder
    {
        return $query->where('plan_id', 0);
    }

    /**
     * Scope to filter banned users
     */
    public function scopeBanned(Builder $query): Builder
    {
        return $query->where('status', Status::USER_BAN);
    }

    /**
     * Scope to filter users with unverified email
     */
    public function scopeEmailUnverified(Builder $query): Builder
    {
        return $query->where('ev', Status::UNVERIFIED);
    }

    /**
     * Scope to filter users with unverified mobile
     */
    public function scopeMobileUnverified(Builder $query): Builder
    {
        return $query->where('sv', Status::UNVERIFIED);
    }

    /**
     * Scope to filter users with unverified KYC
     */
    public function scopeKycUnverified(Builder $query): Builder
    {
        return $query->where('kv', Status::KYC_UNVERIFIED);
    }

    /**
     * Scope to filter users with pending KYC
     */
    public function scopeKycPending(Builder $query): Builder
    {
        return $query->where('kv', Status::KYC_PENDING);
    }

    /**
     * Scope to filter users with verified email
     */
    public function scopeEmailVerified(Builder $query): Builder
    {
        return $query->where('ev', Status::VERIFIED);
    }

    /**
     * Scope to filter users with verified mobile
     */
    public function scopeMobileVerified(Builder $query): Builder
    {
        return $query->where('sv', Status::VERIFIED);
    }

    /**
     * Scope to filter users with balance
     */
    public function scopeWithBalance(Builder $query): Builder
    {
        return $query->where('balance', '>', 0);
    }
}

<?php

namespace App\Constants;

/**
 * Status - Application-wide status constants
 *
 * This class defines all status constants used throughout the application
 * for user verification, payments, tickets, KYC, and other states.
 */
class Status
{
    // General Status
    public const ENABLE = 1;
    public const DISABLE = 0;

    // Yes/No Status
    public const YES = 1;
    public const NO = 0;

    // Verification Status
    public const VERIFIED = 1;
    public const UNVERIFIED = 0;

    // Payment Status
    public const PAYMENT_INITIATE = 0;
    public const PAYMENT_SUCCESS = 1;
    public const PAYMENT_PENDING = 2;
    public const PAYMENT_REJECT = 3;

    // Support Ticket Status
    public const TICKET_OPEN = 0;
    public const TICKET_ANSWER = 1;
    public const TICKET_REPLY = 2;
    public const TICKET_CLOSE = 3;

    // Priority Levels
    public const PRIORITY_LOW = 1;
    public const PRIORITY_MEDIUM = 2;
    public const PRIORITY_HIGH = 3;

    // User Status
    public const USER_ACTIVE = 1;
    public const USER_BAN = 0;

    // KYC (Know Your Customer) Status
    public const KYC_UNVERIFIED = 0;
    public const KYC_PENDING = 2;
    public const KYC_VERIFIED = 1;

    // Payment Gateway Types
    public const GOOGLE_PAY = 5001;

    // Currency Display Types
    public const CUR_BOTH = 1;
    public const CUR_TEXT = 2;
    public const CUR_SYM = 3;

    // Order Status
    public const ORDER_PENDING = 0;
    public const ORDER_SHIPPED = 1;
    public const ORDER_CANCELED = 2;

    // Binary Position
    public const LEFT = 1;
    public const RIGHT = 2;
}

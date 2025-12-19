# Binary Ecom API Documentation

## Overview

This document provides comprehensive information about the Binary Ecom Platform API endpoints.

**Base URL**: `https://yourdomain.com/api/v1`

**Content Type**: `application/json`

**Authentication**: Bearer Token (JWT)

---

## Authentication

### Login

**Endpoint**: `POST /api/v1/auth/login`

**Description**: Authenticate user and receive access token

**Request**:
```json
{
    "email": "user@example.com",
    "password": "password123"
}
```

**Response** (200 OK):
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "username": "johndoe",
            "email": "user@example.com",
            "full_name": "John Doe"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "expires_at": "2025-12-26T12:00:00Z"
    },
    "message": "Login successful"
}
```

### Register

**Endpoint**: `POST /api/v1/auth/register`

**Description**: Register a new user account

**Request**:
```json
{
    "firstname": "John",
    "lastname": "Doe",
    "email": "user@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "referBy": "ref_user",
    "position": 1
}
```

**Response** (201 Created):
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "username": "johndoe",
            "email": "user@example.com",
            "full_name": "John Doe"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "expires_at": "2025-12-26T12:00:00Z"
    },
    "message": "Registration successful"
}
```

---

## User Management

### Get Profile

**Endpoint**: `GET /api/v1/user/profile`

**Authentication**: Required

**Response** (200 OK):
```json
{
    "success": true,
    "data": {
        "id": 1,
        "username": "johndoe",
        "firstname": "John",
        "lastname": "Doe",
        "full_name": "John Doe",
        "email": "user@example.com",
        "balance": 1000.50,
        "email_verified": true,
        "sms_verified": true,
        "kyc_verified": false,
        "created_at": "2025-12-19T10:00:00Z"
    }
}
```

### Update Profile

**Endpoint**: `PUT /api/v1/user/profile`

**Authentication**: Required

**Request**:
```json
{
    "firstname": "John",
    "lastname": "Doe",
    "address": "123 Main St",
    "city": "New York",
    "state": "NY",
    "zip": "10001"
}
```

**Response** (200 OK):
```json
{
    "success": true,
    "data": {
        "id": 1,
        "username": "johndoe",
        "firstname": "John",
        "lastname": "Doe",
        "full_name": "John Doe",
        "email": "user@example.com",
        "balance": 1000.50,
        "email_verified": true,
        "sms_verified": true,
        "kyc_verified": false,
        "address": "123 Main St",
        "city": "New York",
        "state": "NY",
        "zip": "10001",
        "updated_at": "2025-12-19T11:00:00Z"
    },
    "message": "Profile updated successfully"
}
```

---

## Transactions

### Get Transactions

**Endpoint**: `GET /api/v1/transactions`

**Authentication**: Required

**Query Parameters**:
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20, max: 100)
- `remark` (optional): Filter by transaction remark

**Response** (200 OK):
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "user_id": 1,
                "amount": 100.00,
                "charge": 2.50,
                "trx": "TRX123456",
                "trx_type": "+",
                "remark": "deposit",
                "details": "Payment received",
                "created_at": "2025-12-19T10:30:00Z"
            }
        ],
        "first_page_url": "/api/v1/transactions?page=1",
        "from": 1,
        "last_page": 5,
        "last_page_url": "/api/v1/transactions?page=5",
        "next_page_url": "/api/v1/transactions?page=2",
        "path": "/api/v1/transactions",
        "per_page": 20,
        "prev_page_url": null,
        "to": 20,
        "total": 100
    }
}
```

---

## Deposit

### Create Deposit

**Endpoint**: `POST /api/v1/deposit`

**Authentication**: Required

**Request**:
```json
{
    "method": "stripe",
    "amount": 100.00,
    "currency": "USD"
}
```

**Response** (200 OK):
```json
{
    "success": true,
    "data": {
        "trx": "DP123456",
        "amount": 100.00,
        "charge": 2.50,
        "final_amount": 102.50,
        "gateway": {
            "id": 1,
            "name": "Stripe",
            "code": "stripe"
        },
        "payment_url": "https://checkout.stripe.com/pay/cs_..."
    },
    "message": "Deposit request created"
}
```

---

## Withdraw

### Get Withdraw Methods

**Endpoint**: `GET /api/v1/withdraw/methods`

**Authentication**: Required

**Response** (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Bank Transfer",
            "min_limit": 10.00,
            "max_limit": 10000.00,
            "fixed_charge": 5.00,
            "percent_charge": 2.0,
            "currency": "USD"
        }
    ]
}
```

### Create Withdraw Request

**Endpoint**: `POST /api/v1/withdraw`

**Authentication**: Required

**Request**:
```json
{
    "method_id": 1,
    "amount": 100.00,
    "details": "Bank account details..."
}
```

**Response** (200 OK):
```json
{
    "success": true,
    "data": {
        "trx": "WD123456",
        "amount": 100.00,
        "charge": 7.00,
        "final_amount": 93.00,
        "status": "pending",
        "created_at": "2025-12-19T10:30:00Z"
    },
    "message": "Withdraw request submitted"
}
```

---

## Health Check

### System Health

**Endpoint**: `GET /health`

**Authentication**: None

**Response** (200 OK):
```json
{
    "status": "ok",
    "timestamp": "2025-12-19T12:00:00Z",
    "environment": "production",
    "version": "11.0.0",
    "checks": {
        "database": {
            "status": "ok",
            "message": "Database connection successful",
            "response_time_ms": 15.23,
            "connection": "mysql"
        },
        "cache": {
            "status": "ok",
            "message": "Cache connection successful",
            "driver": "redis"
        },
        "disk_space": {
            "status": "ok",
            "message": "Disk space check",
            "total_gb": 100.00,
            "free_gb": 75.50,
            "used_gb": 24.50,
            "used_percentage": 24.5
        },
        "memory": {
            "status": "ok",
            "message": "Memory usage check",
            "current_mb": 128.00,
            "peak_mb": 256.00,
            "limit_mb": 512.00,
            "usage_percentage": 25.0
        },
        "app": {
            "status": "ok",
            "message": "Application is running",
            "uptime": "5 days, 3 hours, 22 minutes",
            "laravel_version": "11.0.0",
            "php_version": "8.2.0",
            "migrations_table": "exists",
            "cache": "ok"
        }
    }
}
```

### System Metrics

**Endpoint**: `GET /health/metrics`

**Authentication**: None

**Response** (200 OK):
```json
{
    "system": {
        "cpu_usage": [0.15, 0.10, 0.05],
        "memory": {
            "used": 134217728,
            "peak": 268435456,
            "limit": 536870912
        }
    },
    "database": {
        "connections": 10,
        "connection_name": "mysql"
    },
    "cache": {
        "driver": "redis",
        "status": "ok"
    },
    "application": {
        "environment": "production",
        "debug": false,
        "url": "https://yourdomain.com"
    }
}
```

---

## Error Responses

### 400 Bad Request

```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password must be at least 8 characters."]
    }
}
```

### 401 Unauthorized

```json
{
    "success": false,
    "message": "Unauthenticated."
}
```

### 403 Forbidden

```json
{
    "success": false,
    "message": "This action is unauthorized."
}
```

### 404 Not Found

```json
{
    "success": false,
    "message": "Resource not found."
}
```

### 422 Validation Error

```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "field": ["Error message"]
    }
}
```

### 429 Too Many Requests

```json
{
    "success": false,
    "message": "Too many requests. Please try again later.",
    "retry_after": 60
}
```

### 500 Internal Server Error

```json
{
    "success": false,
    "message": "An error occurred. Please try again later."
}
```

---

## Rate Limiting

The API implements rate limiting to ensure fair usage:

| Endpoint Category | Rate Limit |
|-------------------|------------|
| Authentication | 5 requests per minute |
| Registration | 3 requests per minute |
| General API | 60 requests per minute |
| Health Check | 120 requests per minute |

**Rate Limit Headers**:
- `X-RateLimit-Limit`: Request limit per window
- `X-RateLimit-Remaining`: Remaining requests in current window
- `X-RateLimit-Reset`: Time when the rate limit resets

---

## Pagination

All list endpoints support pagination using the following parameters:

- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 20, max: 100)

**Response Format**:
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [...],
        "first_page_url": "/api/v1/endpoint?page=1",
        "from": 1,
        "last_page": 5,
        "last_page_url": "/api/v1/endpoint?page=5",
        "next_page_url": "/api/v1/endpoint?page=2",
        "path": "/api/v1/endpoint",
        "per_page": 20,
        "prev_page_url": null,
        "to": 20,
        "total": 100
    }
}
```

---

## SDKs and Code Examples

### PHP (cURL)

```php
<?php

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'https://yourdomain.com/api/v1/auth/login',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode([
        'email' => 'user@example.com',
        'password' => 'password123'
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo 'cURL Error #:' . $err;
} else {
    $data = json_decode($response, true);
    $token = $data['data']['token'];
    
    // Use token for authenticated requests
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://yourdomain.com/api/v1/user/profile',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ],
    ]);
    
    $profileResponse = curl_exec($curl);
    echo $profileResponse;
}
```

### JavaScript (Fetch)

```javascript
// Login
const login = async () => {
    const response = await fetch('https://yourdomain.com/api/v1/auth/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            email: 'user@example.com',
            password: 'password123'
        })
    });
    
    const data = await response.json();
    const token = data.data.token;
    
    // Store token
    localStorage.setItem('token', token);
    
    return token;
};

// Get Profile
const getProfile = async (token) => {
    const response = await fetch('https://yourdomain.com/api/v1/user/profile', {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
        }
    });
    
    const data = await response.json();
    return data;
};
```

---

## Webhooks

The API supports webhooks for real-time notifications of events.

### Supported Events

- `user.registered`: New user registration
- `user.email_verified`: Email verification completed
- `transaction.created`: New transaction created
- `transaction.completed`: Transaction completed
- `withdraw.requested`: Withdraw request submitted
- `withdraw.approved`: Withdraw request approved
- `withdraw.rejected`: Withdraw request rejected

### Webhook Payload Example

```json
{
    "event": "transaction.completed",
    "data": {
        "id": 1,
        "user_id": 1,
        "amount": 100.00,
        "trx": "TRX123456",
        "created_at": "2025-12-19T10:30:00Z"
    },
    "timestamp": "2025-12-19T10:30:00Z"
}
```

### Verifying Webhooks

Each webhook request includes a signature header: `X-Webhook-Signature`

Verify the signature using your webhook secret:

```php
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$payload = file_get_contents('php://input');
$secret = 'your-webhook-secret';

$calculatedSignature = hash_hmac('sha256', $payload, $secret);

if ($signature === $calculatedSignature) {
    // Webhook is valid
    // Process event
}
```

---

## Changelog

### v1.0.0 (2025-12-19)

**Added**:
- Initial API release
- User authentication and registration
- Profile management
- Transaction management
- Deposit and withdraw functionality
- Health check endpoints
- Rate limiting
- Webhook support

---

## Support

For API support, please contact:
- Email: api-support@binaryecom.com
- Documentation: https://docs.binaryecom.com
- Status Page: https://status.binaryecom.com

---

**Last Updated**: 2025-12-19

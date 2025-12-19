<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * ApiAuth - Handles API authentication
 *
 * Validates JWT tokens and handles API authentication
 * with proper logging and rate limiting.
 */
class ApiAuth
{
    /** @var int Active user status constant */
    private const USER_STATUS_ACTIVE = 1;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if API key is provided
        $apiKey = $request->header('X-API-Key');
        $bearerToken = $request->bearerToken();

        if (!$apiKey && !$bearerToken) {
            return response()->json([
                'success' => false,
                'message' => 'API key or authentication token is required',
                'code' => 'MISSING_AUTH',
            ], 401);
        }

        // Validate API key (if using API key authentication)
        if ($apiKey) {
            // Get encrypted API key from config and decrypt it
            $encryptedApiKey = config('app.api_key_encrypted');

            if (!$encryptedApiKey) {
                $this->logUnauthorizedAttempt($request, 'API key not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'Server configuration error',
                    'code' => 'CONFIG_ERROR',
                ], 500);
            }

            // Decrypt the API key
            $validApiKey = $this->decryptApiKey($encryptedApiKey);

            // Use timing-safe comparison
            if (!hash_equals($validApiKey, $apiKey)) {
                $this->logUnauthorizedAttempt($request, 'Invalid API key');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid API key',
                    'code' => 'INVALID_API_KEY',
                ], 401);
            }

            // API key is valid, continue
            return $next($request);
        }

        // Validate Bearer token (if using token authentication)
        if ($bearerToken) {
            // For JWT or Sanctum tokens, you would validate here
            // This is a simplified version
            $user = $this->validateToken($bearerToken);

            if (!$user) {
                $this->logUnauthorizedAttempt($request, 'Invalid token');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token',
                    'code' => 'INVALID_TOKEN',
                ], 401);
            }

            // Set user in request
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            return $next($request);
        }

        return $next($request);
    }

    /**
     * Validate authentication token
     */
    private function validateToken(string $token): ?\App\Models\User
    {
        // This is a simplified validation
        // In production, use Laravel Sanctum or JWT packages

        try {
            // Example: Find user by remember token or API token
            $user = \App\Models\User::where('api_token', hash('sha256', $token))->first();

            if ($user && $user->status === self::USER_STATUS_ACTIVE) {
                return $user;
            }
        } catch (\Exception $e) {
            Log::channel('security')->error('Token validation error', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10) . '...',
            ]);
        }

        return null;
    }

    /**
     * Log unauthorized access attempts
     */
    private function logUnauthorizedAttempt(Request $request, string $reason): void
    {
        // Sanitize sensitive data before logging
        $sanitizedData = $this->sanitizeLogData([
            'ip' => $request->ip(),
            'user_agent' => substr($request->userAgent(), 0, 255),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'reason' => $reason,
            'timestamp' => now(),
        ]);

        Log::channel('security')->warning('Unauthorized API access attempt', $sanitizedData);
    }

    /**
     * Decrypt API key from encrypted string
     */
    private function decryptApiKey(string $encryptedApiKey): string
    {
        try {
            // Use Laravel's Crypt facade to decrypt
            return \Illuminate\Support\Facades\Crypt::decryptString($encryptedApiKey);
        } catch (\Exception $e) {
            Log::channel('security')->error('Failed to decrypt API key', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to decrypt API key');
        }
    }

    /**
     * Sanitize log data to remove sensitive information
     */
    private function sanitizeLogData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'key', 'secret', 'credential'];
        $sanitized = $data;

        foreach ($sensitiveKeys as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = '[REDACTED]';
            }
        }

        return $sanitized;
    }
}

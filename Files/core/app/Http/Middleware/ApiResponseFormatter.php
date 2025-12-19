<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiResponseFormatter - Standardizes API responses
 *
 * Ensures all API responses follow a consistent format with proper
 * error handling, pagination metadata, and security headers.
 */
class ApiResponseFormatter
{
    /** @var int HTTP status code threshold for success responses */
    private const SUCCESS_STATUS_THRESHOLD = 400;

    /** @var int XSS protection header value */
    private const XSS_PROTECTION_HEADER = '1; mode=block';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only process API requests
        if (!$request->is('api/*')) {
            return $next($request);
        }

        $response = $next($request);

        // Get the response content
        $originalContent = $response->getContent();
        $data = json_decode($originalContent, true);

        // If already formatted, return as-is
        if (isset($data['success'])) {
            return $this->addSecurityHeaders($response);
        }

        // Format the response
        if (is_array($data)) {
            // Check if it's a paginated response
            if ($this->isPaginated($data)) {
                $formatted = $this->formatPaginatedResponse($data, $request);
            } else {
                $formatted = $this->formatStandardResponse($data, $response->getStatusCode());
            }

            $response->setContent(json_encode($formatted));
        } else {
            $formatted = [
                'success' => $response->getStatusCode() < self::SUCCESS_STATUS_THRESHOLD,
                'data' => $data,
                'message' => $response->getStatusCode() < self::SUCCESS_STATUS_THRESHOLD ? 'Success' : 'Error',
            ];
            $response->setContent(json_encode($formatted));
        }

        return $this->addSecurityHeaders($response);
    }

    /**
     * Check if response is paginated
     */
    private function isPaginated(array $data): bool
    {
        return isset($data['current_page'], $data['data'], $data['total']);
    }

    /**
     * Format paginated response
     */
    private function formatPaginatedResponse(array $data, Request $request): array
    {
        return [
            'success' => true,
            'data' => $data['data'],
            'pagination' => [
                'current_page' => $data['current_page'],
                'from' => $data['from'],
                'to' => $data['to'],
                'per_page' => $data['per_page'],
                'total' => $data['total'],
                'last_page' => $data['last_page'],
                'first_page_url' => $data['first_page_url'],
                'last_page_url' => $data['last_page_url'],
                'next_page_url' => $data['next_page_url'],
                'prev_page_url' => $data['prev_page_url'],
                'path' => $data['path'],
            ],
            'message' => 'Data retrieved successfully',
        ];
    }

    /**
     * Format standard response
     */
    private function formatStandardResponse(array $data, int $statusCode): array
    {
        $isSuccess = $statusCode < self::SUCCESS_STATUS_THRESHOLD;

        return [
            'success' => $isSuccess,
            'data' => $data,
            'message' => $isSuccess ? 'Success' : 'An error occurred',
            'status_code' => $statusCode,
        ];
    }

    /**
     * Add security headers to API responses
     */
    private function addSecurityHeaders(Response $response): Response
    {
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', self::XSS_PROTECTION_HEADER);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}

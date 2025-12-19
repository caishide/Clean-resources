<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class CheckImpersonation
{
    /** @var int Default expiration timestamp */
    private const DEFAULT_EXPIRES_AT = 0;

    /** @var int Default start time for session calculation */
    private const DEFAULT_START_TIME = 0;

    /** @var int Seconds per minute for conversion */
    private const SECONDS_PER_MINUTE = 60;

    /** @var int Decimal places for session duration calculation */
    private const DURATION_DECIMAL_PLACES = 2;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is impersonating
        if (Session::has('is_impersonating') && Session::get('is_impersonating')) {
            $impersonatorData = Session::get('impersonator_data');

            // Check if impersonation has expired
            $expiresAt = $impersonatorData['impersonation_expires_at'] ?? self::DEFAULT_EXPIRES_AT;

            if (time() > $expiresAt) {
                // Impersonation has expired, log out and return to admin panel
                $this->forceLogoutFromImpersonation('Impersonation session has expired');

                return redirect()
                    ->route('admin.dashboard')
                    ->with('notify', [['error', 'Impersonation session has expired. Please start a new session if needed.']]);
            }

            // Log all actions taken during impersonation
            $this->logImpersonationAction($request);

            // Add headers to indicate impersonation for additional security
            $response = $next($request);

            $response->headers->set('X-Impersonated-Session', 'true');
            $response->headers->set('X-Original-Admin-ID', $impersonatorData['admin_id'] ?? 'unknown');

            // Add warning headers for security monitoring
            $response->headers->set('X-Admin-Impersonation-Warning', 'This session is being monitored for admin impersonation');

            return $response;
        }

        return $next($request);
    }

    /**
     * Force logout from impersonation mode
     *
     * @param string $reason
     * @return void
     */
    private function forceLogoutFromImpersonation(string $reason): void
    {
        // Log the forced logout
        $impersonatorData = Session::get('impersonator_data');

        if ($impersonatorData) {
            $adminId = $impersonatorData['admin_id'];
            $currentUser = Auth::user();

            // Log the forced end of impersonation
            app(\App\Models\AuditLog::class)::create([
                'admin_id' => $adminId,
                'action_type' => 'admin_impersonation_forced_end',
                'entity_type' => 'User',
                'entity_id' => $currentUser ? $currentUser->id : null,
                'meta' => [
                    'reason' => $reason,
                    'session_duration_minutes' => $this->calculateSessionDuration($impersonatorData),
                    'forced' => true,
                    'ended_at' => now()->toISOString(),
                ]
            ]);
        }

        // Clear all impersonation-related session data
        Session::forget('is_impersonating');
        Session::forget('impersonator_data');
        Session::forget('impersonation_reason');
        Session::forget('impersonation_started_at');

        // Log out from user session
        Auth::logout();
    }

    /**
     * Log actions taken during impersonation
     *
     * @param Request $request
     * @return void
     */
    private function logImpersonationAction(Request $request): void
    {
        // Only log significant actions (POST, PUT, DELETE, PATCH)
        $significantMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        if (in_array($request->method(), $significantMethods)) {
            $impersonatorData = Session::get('impersonator_data');
            $currentUser = Auth::user();

            if ($impersonatorData && $currentUser) {
                app(\App\Models\AuditLog::class)::create([
                    'admin_id' => $impersonatorData['admin_id'],
                    'action_type' => 'admin_impersonation_action',
                    'entity_type' => 'User',
                    'entity_id' => $currentUser->id,
                    'meta' => [
                        'method' => $request->method(),
                        'path' => $request->path(),
                        'route_name' => $request->route()?->getName(),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'timestamp' => now()->toISOString(),
                    ]
                ]);
            }
        }
    }

    /**
     * Calculate session duration in minutes
     *
     * @param array $impersonatorData
     * @return float
     */
    private function calculateSessionDuration(array $impersonatorData): float
    {
        $startTime = $impersonatorData['impersonation_start_time'] ?? self::DEFAULT_START_TIME;
        $endTime = time();
        return round(($endTime - $startTime) / self::SECONDS_PER_MINUTE, self::DURATION_DECIMAL_PLACES);
    }
}

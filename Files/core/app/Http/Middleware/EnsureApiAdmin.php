<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated API user is an Admin
 *
 * This middleware checks if the Sanctum-authenticated token
 * belongs to an Admin model, not a regular User.
 */
class EnsureApiAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if authenticated
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Check if the authenticated model is an Admin
        if (!($user instanceof Admin)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        return $next($request);
    }
}

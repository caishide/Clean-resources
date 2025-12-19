<?php

namespace App\Services;

use Illuminate\Http\RedirectResponse;

/**
 * NotificationService - Centralized notification handling
 *
 * Provides a unified interface for displaying success, error, and warning messages
 * across the application, eliminating code duplication in controllers.
 */
class NotificationService
{
    /**
     * Display an error notification
     *
     * @param string $message The error message to display
     * @param string|null $route Optional route to redirect to (null for back())
     * @return RedirectResponse
     */
    public static function error(string $message, ?string $route = null): RedirectResponse
    {
        $notify = [['error', $message]];
        return $route ? redirect($route)->withNotify($notify) : back()->withNotify($notify);
    }

    /**
     * Display a success notification
     *
     * @param string $message The success message to display
     * @param string|null $route Optional route to redirect to (null for back())
     * @return RedirectResponse
     */
    public static function success(string $message, ?string $route = null): RedirectResponse
    {
        $notify = [['success', $message]];
        return $route ? redirect($route)->withNotify($notify) : back()->withNotify($notify);
    }

    /**
     * Display a warning notification
     *
     * @param string $message The warning message to display
     * @param string|null $route Optional route to redirect to (null for back())
     * @return RedirectResponse
     */
    public static function warning(string $message, ?string $route = null): RedirectResponse
    {
        $notify = [['warning', $message]];
        return $route ? redirect($route)->withNotify($notify) : back()->withNotify($notify);
    }

    /**
     * Display an info notification
     *
     * @param string $message The info message to display
     * @param string|null $route Optional route to redirect to (null for back())
     * @return RedirectResponse
     */
    public static function info(string $message, ?string $route = null): RedirectResponse
    {
        $notify = [['info', $message]];
        return $route ? redirect($route)->withNotify($notify) : back()->withNotify($notify);
    }

    /**
     * Display multiple notifications at once
     *
     * @param array $notifications Array of notifications, each being ['type', 'message']
     * @param string|null $route Optional route to redirect to (null for back())
     * @return RedirectResponse
     */
    public static function multiple(array $notifications, ?string $route = null): RedirectResponse
    {
        return $route ? redirect($route)->withNotify($notifications) : back()->withNotify($notifications);
    }
}

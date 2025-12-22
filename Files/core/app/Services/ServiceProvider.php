<?php

namespace App\Services;

use Illuminate\Support\ServiceProvider as BaseProvider;

class ServiceProvider extends BaseProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register UserService as singleton
        $this->app->singleton(UserService::class, function ($app) {
            return new UserService();
        });

        // Register TransactionService with dependency
        $this->app->singleton(TransactionService::class, function ($app) {
            return new TransactionService(
                $app->make(UserService::class)
            );
        });

        // Register OrderService with dependencies
        $this->app->singleton(OrderService::class, function ($app) {
            return new OrderService(
                $app->make(UserService::class),
                $app->make(TransactionService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

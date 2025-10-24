<?php

namespace Pollora\Exceptions;

use Illuminate\Support\ServiceProvider;
use Pollora\Exceptions\WordPressErrorHandler;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;

/**
 * WordPress Service Provider
 *
 * Registers and bootstraps WordPress-specific functionality within the Laravel application.
 * This includes error handling, hooks, and filters for WordPress integration.
 *
 * @package App\Providers
 */
class WordPressErrorServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     *
     * Registers the WordPressErrorHandler as a singleton to ensure only one
     * instance exists throughout the application lifecycle.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(WordPressErrorHandler::class);
    }

    /**
     * Bootstrap application services.
     *
     * Sets up WordPress error handling by registering hooks and filters.
     * Only executes if WordPress is loaded (checks for add_action function).
     * Uses dependency injection to resolve all required services.
     *
     * @param WordPressErrorHandler $errorHandler The WordPress error handler instance
     * @param Action $action The WordPress action service
     * @param Filter $filter The WordPress filter service
     *
     * @return void
     */
    public function boot(
        WordPressErrorHandler $errorHandler,
        Action $action,
        Filter $filter
    ): void {
        // Register WordPress error handling hooks
        $errorHandler->register($action, $filter);
    }
}

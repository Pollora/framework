<?php

namespace Pollora\Exceptions;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Psr\Log\LoggerInterface;

/**
 * WordPress Error Handler
 *
 * Intercepts and logs WordPress errors (_doing_it_wrong, deprecated functions, etc.)
 * without triggering PHP errors or breaking the application flow.
 *
 * This handler hooks into WordPress error actions and filters to capture errors,
 * log them to a dedicated channel, and prevent them from being displayed to users.
 *
 * @package Pollora\Exceptions
 */
class WordPressErrorHandler
{
    /**
     * The logger instance for WordPress channel.
     */
    private readonly LoggerInterface $logger;

    /**
     * Create a new WordPress error handler instance.
     *
     * @param Application $app The Laravel application instance
     * @param LogManager $logManager The log manager to access specific channels
     * @param Request $request The current HTTP request
     */
    public function __construct(
        private readonly Application $app,
        LogManager $logManager,
        private readonly Request $request
    ) {
        // Get the WordPress channel logger specifically
        $this->logger = $logManager->channel('wordpress');
    }

    /**
     * Register WordPress hooks for error handling.
     *
     * This method sets up all necessary WordPress actions and filters to intercept
     * errors before they are triggered as PHP errors. It handles:
     * - Incorrect function usage (_doing_it_wrong)
     * - Deprecated functions
     * - Deprecated arguments
     *
     * @param Action $action The WordPress action service
     * @param Filter $filter The WordPress filter service
     *
     * @return void
     */
    public function register(Action $action, Filter $filter): void
    {
        // Register action hooks to capture error information
        $action->add('doing_it_wrong_run', [$this, 'handleDoingItWrong'], 10, 3);
        $action->add('deprecated_function_run', [$this, 'handleDeprecatedFunction'], 10, 3);
        $action->add('deprecated_argument_run', [$this, 'handleDeprecatedArgument'], 10, 3);

        // Register filter hooks to disable PHP error triggering
        // Using PHP_INT_MAX priority to ensure these filters run last
        $filter->add('doing_it_wrong_trigger_error', [$this, 'disableTriggerError'], PHP_INT_MAX, 4);
        $filter->add('deprecated_function_trigger_error', [$this, 'disableTriggerError'], PHP_INT_MAX, 4);
        $filter->add('deprecated_argument_trigger_error', [$this, 'disableTriggerError'], PHP_INT_MAX, 4);
    }

    /**
     * Handle WordPress "doing it wrong" errors.
     *
     * Called when a WordPress function is used incorrectly. Logs the error
     * with full context including the function name, error message, version,
     * and request details.
     *
     * @param string $function_name The name of the function that was called incorrectly
     * @param string $message Explanation of what was done incorrectly (may contain HTML)
     * @param string $version The WordPress version where this error was added
     *
     * @return void
     */
    public function handleDoingItWrong(string $function_name, string $message, string $version): void
    {
        // Remove HTML tags from the error message for cleaner logs
        $cleanMessage = strip_tags($message);

        // Build context array with all relevant information
        $context = [
            'type' => 'doing_it_wrong',
            'function' => $function_name,
            'version' => $version,
            'message' => $cleanMessage,
            'url' => $this->request->fullUrl(),
            'method' => $this->request->method(),
            'ip' => $this->request->ip(),
        ];

        // Add backtrace only in local environment to avoid performance overhead
        if ($this->app->environment('local')) {
            $context['backtrace'] = $this->getCleanBacktrace();
        }

        // Log as warning since it indicates improper usage but doesn't break functionality
        $this->logger->warning(
            "WordPress: {$function_name} called incorrectly",
            $context
        );
    }

    /**
     * Handle deprecated WordPress function usage.
     *
     * Called when a deprecated WordPress function is used. Logs the function name,
     * its replacement (if available), and the version where it was deprecated.
     *
     * @param string $function_name The deprecated function name
     * @param string $replacement The recommended replacement function (empty string if none)
     * @param string $version The WordPress version where the function was deprecated
     *
     * @return void
     */
    public function handleDeprecatedFunction(string $function_name, string $replacement, string $version): void
    {
        $context = [
            'type' => 'deprecated_function',
            'function' => $function_name,
            'replacement' => $replacement ?: 'no alternative available',
            'version' => $version,
            'url' => $this->request->fullUrl(),
        ];

        // Add backtrace only in local environment
        if ($this->app->environment('local')) {
            $context['backtrace'] = $this->getCleanBacktrace();
        }

        // Log as info since deprecated functions still work but should be updated
        $this->logger->info(
            "WordPress: Deprecated function {$function_name} used",
            $context
        );
    }

    /**
     * Handle deprecated WordPress function arguments.
     *
     * Called when a function is called with deprecated arguments. Logs which
     * function was called and what argument usage is deprecated.
     *
     * @param string $function_name The function with deprecated argument usage
     * @param string $message Explanation of which argument is deprecated (may contain HTML)
     * @param string $version The WordPress version where the argument was deprecated
     *
     * @return void
     */
    public function handleDeprecatedArgument(string $function_name, string $message, string $version): void
    {
        // Remove HTML tags from the error message
        $cleanMessage = strip_tags($message);

        $context = [
            'type' => 'deprecated_argument',
            'function' => $function_name,
            'version' => $version,
            'message' => $cleanMessage,
            'url' => $this->request->fullUrl(),
        ];

        // Add backtrace only in local environment
        if ($this->app->environment('local')) {
            $context['backtrace'] = $this->getCleanBacktrace();
        }

        // Log as info since deprecated arguments still work but should be updated
        $this->logger->info(
            "WordPress: Deprecated argument in {$function_name}",
            $context
        );
    }

    /**
     * Disable WordPress error triggering.
     *
     * This filter callback always returns false to prevent WordPress from
     * calling trigger_error() and displaying PHP errors to users.
     *
     * @return bool Always returns false to disable error triggering
     */
    public function disableTriggerError(): bool
    {
        return false;
    }

    /**
     * Get a cleaned and formatted backtrace.
     *
     * Generates a backtrace excluding internal ErrorHandler calls and formats
     * it as a readable array of strings. Limited to 10 frames for performance.
     *
     * @return array<int, string> Array of formatted backtrace lines
     */
    private function getCleanBacktrace(): array
    {
        // Get backtrace without arguments to reduce memory usage
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $cleanTrace = [];

        foreach ($trace as $index => $item) {
            // Skip internal ErrorHandler calls to reduce noise
            if (isset($item['class']) && $item['class'] === self::class) {
                continue;
            }

            // Format each trace line consistently
            $cleanTrace[] = sprintf(
                '#%d %s%s%s() in %s:%d',
                $index,
                $item['class'] ?? '',
                isset($item['class']) ? $item['type'] : '',
                $item['function'] ?? 'unknown',
                $item['file'] ?? 'unknown',
                $item['line'] ?? 0
            );
        }

        return $cleanTrace;
    }
}

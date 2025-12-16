<?php

declare(strict_types=1);

namespace Pollora\Logging\Application\Services;

use Pollora\Logging\Domain\Contracts\LoggerInterface;
use Pollora\Logging\Domain\Enums\LogLevel;
use Pollora\Logging\Domain\ValueObjects\LogContext;
use Throwable;

/**
 * Application logging service for Pollora.
 *
 * Provides a simplified API for logging within the framework
 * with generic methods for all log levels.
 *
 * @api
 */
final readonly class LoggingService
{
    /**
     * @param  LoggerInterface  $logger  The underlying logger implementation
     */
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * Log an emergency message.
     *
     * @param  string  $message  The emergency message
     * @param  LogContext|null  $context  The log context (optional)
     * @param  Throwable|null  $exception  The exception to log (optional)
     */
    public function emergency(string $message, ?LogContext $context = null, ?Throwable $exception = null): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context, $exception);
    }

    /**
     * Log an alert message.
     *
     * @param  string  $message  The alert message
     * @param  LogContext|null  $context  The log context (optional)
     * @param  Throwable|null  $exception  The exception to log (optional)
     */
    public function alert(string $message, ?LogContext $context = null, ?Throwable $exception = null): void
    {
        $this->log(LogLevel::ALERT, $message, $context, $exception);
    }

    /**
     * Log a critical error.
     *
     * @param  string  $message  The critical message
     * @param  LogContext|null  $context  The log context (optional)
     * @param  Throwable|null  $exception  The exception to log (optional)
     */
    public function critical(string $message, ?LogContext $context = null, ?Throwable $exception = null): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context, $exception);
    }

    /**
     * Log an error with full context.
     *
     * @param  string  $message  The error message
     * @param  LogContext|null  $context  The log context (optional)
     * @param  Throwable|null  $exception  The exception to log (optional)
     */
    public function error(string $message, ?LogContext $context = null, ?Throwable $exception = null): void
    {
        $this->log(LogLevel::ERROR, $message, $context, $exception);
    }

    /**
     * Log a warning.
     *
     * @param  string  $message  The warning message
     * @param  LogContext|null  $context  The log context (optional)
     */
    public function warning(string $message, ?LogContext $context = null): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Log a notice.
     *
     * @param  string  $message  The notice message
     * @param  LogContext|null  $context  The log context (optional)
     */
    public function notice(string $message, ?LogContext $context = null): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Log information.
     *
     * @param  string  $message  The info message
     * @param  LogContext|null  $context  The log context (optional)
     */
    public function info(string $message, ?LogContext $context = null): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Log a debug message (only if debug is enabled).
     *
     * @param  string  $message  The debug message
     * @param  LogContext|null  $context  The log context (optional)
     */
    public function debug(string $message, ?LogContext $context = null): void
    {
        if (! $this->logger->isDebugEnabled()) {
            return;
        }

        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Generic logging method.
     *
     * @param  LogLevel  $level  The log level
     * @param  string  $message  The message to log
     * @param  LogContext|null  $context  The log context (optional)
     * @param  Throwable|null  $exception  The exception to log (optional)
     */
    private function log(
        LogLevel $level,
        string $message,
        ?LogContext $context = null,
        ?Throwable $exception = null
    ): void {
        $contextArray = $context?->toArray() ?? [];

        if ($exception instanceof \Throwable && ! isset($contextArray['exception'])) {
            $contextArray['exception'] = $exception;
        }

        $this->logger->logWithModule($level->value, $message, $contextArray);
    }

    /**
     * Get the underlying logger instance.
     *
     * @return LoggerInterface The logger instance
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Check if debug logging is enabled.
     *
     * @return bool True if debug is enabled
     */
    public function isDebugEnabled(): bool
    {
        return $this->logger->isDebugEnabled();
    }

    /**
     * Get the channel name being used for logging.
     *
     * @return string The channel name
     */
    public function getChannelName(): string
    {
        return $this->logger->getChannelName();
    }
}

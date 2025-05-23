<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Contracts\ShutdownHandlerInterface;

/**
 * Service for handling WordPress shutdown actions.
 */
class ShutdownHandlerService implements ShutdownHandlerInterface
{
    /**
     * Content types that should be processed.
     *
     * @var array<string>
     */
    protected array $validContentTypes = ['text/html', 'text/html; charset=UTF-8'];

    /**
     * Execute shutdown actions and return potentially modified content.
     *
     * @param  string  $content  The original response content
     * @return string The processed content after running shutdown actions
     */
    public function executeShutdownActions(string $content): string
    {
        // If WordPress shutdown is not available, return original content
        if (! $this->isShutdownFunctionAvailable()) {
            return $content;
        }

        // Buffer the output to capture WordPress shutdown actions
        ob_start();
        echo $content;

        // Execute WordPress shutdown actions
        $this->runShutdownHook();

        // Get the modified content
        $modifiedContent = ob_get_clean();

        return $modifiedContent ?: $content;
    }

    /**
     * Check if this content type should be processed by shutdown handlers.
     *
     * @param  string  $contentType  The response content type
     * @return bool True if the content should be processed
     */
    public function shouldProcessContentType(string $contentType): bool
    {
        // Direct match is faster than strpos for known types
        if (in_array($contentType, $this->validContentTypes, true)) {
            return true;
        }

        // Fallback to partial match for other html content types
        return str_contains($contentType, 'text/html');
    }

    /**
     * Check if the WordPress shutdown function is available.
     *
     * @return bool True if the function exists
     */
    protected function isShutdownFunctionAvailable(): bool
    {
        return function_exists('shutdown_action_hook');
    }

    /**
     * Run the WordPress shutdown hook.
     */
    protected function runShutdownHook(): void
    {
        if ($this->isShutdownFunctionAvailable()) {
            shutdown_action_hook();
        }
    }
}

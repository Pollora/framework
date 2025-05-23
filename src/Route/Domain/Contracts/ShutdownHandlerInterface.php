<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

/**
 * Interface for shutdown handlers.
 *
 * Defines methods for executing cleanup actions before a response is sent.
 */
interface ShutdownHandlerInterface
{
    /**
     * Execute shutdown actions and return potentially modified content.
     *
     * @param  string  $content  The original response content
     * @return string The processed content after running shutdown actions
     */
    public function executeShutdownActions(string $content): string;

    /**
     * Check if this content type should be processed by shutdown handlers.
     *
     * @param  string  $contentType  The response content type
     * @return bool True if the content should be processed
     */
    public function shouldProcessContentType(string $contentType): bool;
}

<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

/**
 * Interface for handling WordPress shutdown actions.
 */
interface ShutdownHandlerInterface
{
    /**
     * Execute shutdown actions and return potentially modified content.
     *
     * @param string $content The original response content
     * @return string The processed content after running shutdown actions
     */
    public function executeShutdownActions(string $content): string;
    
    /**
     * Check if this content type should be processed by shutdown handlers.
     *
     * @param string $contentType The response content type
     * @return bool True if the content should be processed
     */
    public function shouldProcessContentType(string $contentType): bool;
    
    /**
     * Execute WordPress shutdown hook directly.
     * 
     * This method is used to directly run the WordPress shutdown hook
     * without any output buffering or content modification.
     * 
     * @return void
     */
    public function executeWordPressShutdownHook(): void;
} 
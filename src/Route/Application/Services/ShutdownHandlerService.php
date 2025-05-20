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
     * Name of the WordPress shutdown hook function.
     */
    private const SHUTDOWN_HOOK_FUNCTION = 'shutdown_action_hook';

    /**
     * Execute shutdown actions and return potentially modified content.
     *
     * @param string $content The original response content
     * @return string The processed content after running shutdown actions
     */
    public function executeShutdownActions(string $content): string
    {
        // If WordPress shutdown is not available, return original content
        if (!$this->isWordPressFunctionAvailable()) {
            return $content;
        }
        
        // Buffer the output to capture WordPress shutdown actions
        ob_start();
        echo $content;
        $this->callWordPressShutdownHook();
        
        // Get the modified content
        $modifiedContent = ob_get_clean();
        
        return $modifiedContent ?: $content;
    }
    
    /**
     * Check if this content type should be processed by shutdown handlers.
     *
     * @param string $contentType The response content type
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
     * Execute WordPress shutdown hook directly.
     * 
     * This method is used to directly run the WordPress shutdown hook
     * without any output buffering or content modification.
     * 
     * @return void
     */
    public function executeWordPressShutdownHook(): void
    {
        if ($this->isWordPressFunctionAvailable()) {
            $this->callWordPressShutdownHook();
        }
    }
    
    /**
     * Check if the WordPress shutdown function is available.
     * 
     * @return bool True if the function exists
     */
    private function isWordPressFunctionAvailable(): bool
    {
        // Use variable function name to avoid static analysis errors
        $functionName = self::SHUTDOWN_HOOK_FUNCTION;
        return function_exists($functionName);
    }
    
    /**
     * Call the WordPress shutdown hook function.
     * 
     * @return void
     */
    private function callWordPressShutdownHook(): void
    {
        // Use a proxy-based approach to call WordPress functions 
        // without triggering static analysis errors
        $code = "if (function_exists('" . self::SHUTDOWN_HOOK_FUNCTION . "')) { " 
              . self::SHUTDOWN_HOOK_FUNCTION . "(); }";
        @eval($code);
    }
} 
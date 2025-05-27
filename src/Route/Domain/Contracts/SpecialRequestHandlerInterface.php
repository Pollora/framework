<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Domain\Models\SpecialRequest;

/**
 * Port for handling WordPress special requests
 * 
 * Manages special WordPress requests like robots.txt, favicon.ico,
 * feeds, and other WordPress-specific endpoints.
 */
interface SpecialRequestHandlerInterface
{
    /**
     * Check if handler can handle the special request
     * 
     * @param SpecialRequest $request The special request to check
     * @return bool True if request can be handled
     */
    public function canHandle(SpecialRequest $request): bool;

    /**
     * Handle the special request
     * 
     * @param SpecialRequest $request The special request to handle
     * @return mixed The response from handling the request
     */
    public function handle(SpecialRequest $request): mixed;

    /**
     * Find an explicitly defined route for the special request
     * 
     * @param SpecialRequest $request The special request
     * @return Route|null The explicit route if found
     */
    public function findExplicitRoute(SpecialRequest $request): ?Route;

    /**
     * Register a custom handler for a special request type
     * 
     * @param string $type The special request type
     * @param callable $handler The handler function
     * @return void
     */
    public function registerHandler(string $type, callable $handler): void;

    /**
     * Get all supported special request types
     * 
     * @return array Array of supported types
     */
    public function getSupportedTypes(): array;

    /**
     * Check if a type is supported
     * 
     * @param string $type The type to check
     * @return bool True if type is supported
     */
    public function supportsType(string $type): bool;

    /**
     * Get the default WordPress handler for a request type
     * 
     * @param string $type The request type
     * @return callable|null The default handler if available
     */
    public function getDefaultHandler(string $type): ?callable;

    /**
     * Set explicit routes for special requests
     * 
     * @param array $routes Map of special request types to routes
     * @return void
     */
    public function setExplicitRoutes(array $routes): void;
}
<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Contracts\SpecialRequestHandlerInterface;
use Pollora\Route\Domain\Models\SpecialRequest;
use Pollora\Route\Domain\Services\SpecialRequestDetector;

/**
 * Service for handling WordPress special requests
 * 
 * Manages the processing of special WordPress requests like robots.txt,
 * favicon.ico, feeds, and other WordPress-specific endpoints.
 */
final class HandleSpecialRequestService
{
    public function __construct(
        private readonly SpecialRequestHandlerInterface $specialRequestHandler,
        private readonly SpecialRequestDetector $specialRequestDetector,
        private readonly array $config = []
    ) {}

    /**
     * Handle a special request from context
     * 
     * @param array $context Request context
     * @return mixed Response from handling the request, or null if not handled
     */
    public function execute(array $context): mixed
    {
        // Detect if this is a special request
        $specialRequest = $this->specialRequestDetector->detect($context);
        
        if (!$specialRequest) {
            return null;
        }
        
        // Check if we should handle this request
        if (!$this->shouldHandle($specialRequest)) {
            return null;
        }
        
        // Handle the request
        return $this->handleRequest($specialRequest);
    }

    /**
     * Handle a specific special request
     * 
     * @param SpecialRequest $request The special request to handle
     * @return mixed Response from handling the request
     */
    public function handleRequest(SpecialRequest $request): mixed
    {
        // Check for explicit route first
        $explicitRoute = $this->specialRequestHandler->findExplicitRoute($request);
        if ($explicitRoute) {
            return $this->executeRouteAction($explicitRoute, $request);
        }
        
        // Use default handler
        if ($this->specialRequestHandler->canHandle($request)) {
            return $this->specialRequestHandler->handle($request);
        }
        
        // Fallback to WordPress default behavior
        return $this->handleWithWordPressDefault($request);
    }

    /**
     * Check if a URI represents a special request
     * 
     * @param string $uri Request URI
     * @return bool True if URI is a special request
     */
    public function isSpecialRequest(string $uri): bool
    {
        $context = ['uri' => $uri];
        return $this->specialRequestDetector->detect($context) !== null;
    }

    /**
     * Get the special request type for a URI
     * 
     * @param string $uri Request URI
     * @return string|null Special request type or null
     */
    public function getSpecialRequestType(string $uri): ?string
    {
        return $this->specialRequestDetector->detectTypeFromUri($uri);
    }

    /**
     * Handle multiple special requests in batch
     * 
     * @param array $contexts Array of request contexts
     * @return array Array of responses
     */
    public function handleMultiple(array $contexts): array
    {
        $responses = [];
        
        foreach ($contexts as $context) {
            $responses[] = $this->execute($context);
        }
        
        return $responses;
    }

    /**
     * Register a custom handler for a special request type
     * 
     * @param string $type Special request type
     * @param callable $handler Handler function
     * @return void
     */
    public function registerCustomHandler(string $type, callable $handler): void
    {
        $this->specialRequestHandler->registerHandler($type, $handler);
    }

    /**
     * Get all supported special request types
     * 
     * @return array Array of supported types
     */
    public function getSupportedTypes(): array
    {
        return $this->specialRequestHandler->getSupportedTypes();
    }

    /**
     * Check if a specific type is supported
     * 
     * @param string $type Type to check
     * @return bool True if type is supported
     */
    public function supportsType(string $type): bool
    {
        return $this->specialRequestHandler->supportsType($type);
    }

    /**
     * Get debug information for special request handling
     * 
     * @param array $context Request context
     * @return array Debug information
     */
    public function getDebugInfo(array $context): array
    {
        $specialRequest = $this->specialRequestDetector->detect($context);
        
        return [
            'is_special_request' => $specialRequest !== null,
            'special_request' => $specialRequest?->toArray(),
            'should_handle' => $specialRequest ? $this->shouldHandle($specialRequest) : false,
            'has_explicit_route' => $specialRequest ? $this->specialRequestHandler->findExplicitRoute($specialRequest) !== null : false,
            'can_handle' => $specialRequest ? $this->specialRequestHandler->canHandle($specialRequest) : false,
            'supported_types' => $this->getSupportedTypes(),
            'context' => $context,
        ];
    }

    /**
     * Determine if we should handle the special request
     * 
     * @param SpecialRequest $request The special request
     * @return bool True if we should handle it
     */
    private function shouldHandle(SpecialRequest $request): bool
    {
        // Check if request should use WordPress default
        if ($request->shouldUseWordPressDefault()) {
            return false;
        }
        
        // Check if we have an explicit route or handler
        return $this->specialRequestHandler->findExplicitRoute($request) !== null
            || $this->specialRequestHandler->canHandle($request);
    }

    /**
     * Execute a route action for a special request
     * 
     * @param mixed $route The route to execute
     * @param SpecialRequest $request The special request
     * @return mixed Response from route execution
     */
    private function executeRouteAction($route, SpecialRequest $request): mixed
    {
        $action = $route->getAction();
        
        if (is_callable($action)) {
            return call_user_func($action, $request);
        }
        
        if (is_string($action) && class_exists($action)) {
            $instance = new $action();
            if (method_exists($instance, '__invoke')) {
                return $instance($request);
            }
        }
        
        if (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;
            if (is_string($controller) && class_exists($controller)) {
                $instance = new $controller();
                if (method_exists($instance, $method)) {
                    return $instance->$method($request);
                }
            }
        }
        
        return $action;
    }

    /**
     * Handle special request with WordPress default behavior
     * 
     * @param SpecialRequest $request The special request
     * @return mixed WordPress default response
     */
    private function handleWithWordPressDefault(SpecialRequest $request): mixed
    {
        $type = $request->getType();
        
        return match ($type) {
            'robots' => $this->handleRobotsDefault(),
            'favicon' => $this->handleFaviconDefault(),
            'feed' => $this->handleFeedDefault(),
            'trackback' => $this->handleTrackbackDefault(),
            default => null
        };
    }

    /**
     * Handle robots.txt with WordPress default
     */
    private function handleRobotsDefault(): mixed
    {
        if (function_exists('do_robots')) {
            return do_robots();
        }
        return null;
    }

    /**
     * Handle favicon.ico with WordPress default
     */
    private function handleFaviconDefault(): mixed
    {
        if (function_exists('do_favicon')) {
            return do_favicon();
        }
        return null;
    }

    /**
     * Handle feed with WordPress default
     */
    private function handleFeedDefault(): mixed
    {
        if (function_exists('do_feed')) {
            return do_feed();
        }
        return null;
    }

    /**
     * Handle trackback with WordPress default
     */
    private function handleTrackbackDefault(): mixed
    {
        if (function_exists('do_trackback')) {
            return do_trackback();
        }
        return null;
    }
}
<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\WordPress;

use Pollora\Route\Domain\Contracts\SpecialRequestHandlerInterface;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Domain\Models\SpecialRequest;

/**
 * WordPress special request handler implementation
 *
 * Handles WordPress special requests like robots.txt, favicon.ico,
 * feeds, and other WordPress-specific endpoints.
 */
final class WordPressSpecialRequestHandler implements SpecialRequestHandlerInterface
{
    private array $customHandlers = [];
    private array $explicitRoutes = [];

    public function __construct(array $config = [])
    {
        $this->loadConfiguration($config);
    }

    /**
     * Check if handler can handle the special request
     */
    public function canHandle(SpecialRequest $request): bool
    {
        $type = $request->getType();

        // Check custom handlers first
        if (isset($this->customHandlers[$type])) {
            return true;
        }

        // Check WordPress default handlers
        return $this->hasWordPressDefaultHandler($type);
    }

    /**
     * Handle the special request
     */
    public function handle(SpecialRequest $request): mixed
    {
        $type = $request->getType();

        // Use custom handler if available
        if (isset($this->customHandlers[$type])) {
            return $this->executeCustomHandler($type, $request);
        }

        // Use WordPress default handler
        return $this->executeWordPressDefault($type, $request);
    }

    /**
     * Find an explicitly defined route for the special request
     */
    public function findExplicitRoute(SpecialRequest $request): ?Route
    {
        $type = $request->getType();
        return $this->explicitRoutes[$type] ?? null;
    }

    /**
     * Register a custom handler for a special request type
     */
    public function registerHandler(string $type, callable $handler): void
    {
        $this->customHandlers[$type] = $handler;
    }

    /**
     * Get all supported special request types
     */
    public function getSupportedTypes(): array
    {
        $wpTypes = ['robots', 'favicon', 'feed', 'trackback', 'xmlrpc', 'pingback'];
        $customTypes = array_keys($this->customHandlers);

        return array_unique(array_merge($wpTypes, $customTypes));
    }

    /**
     * Check if a type is supported
     */
    public function supportsType(string $type): bool
    {
        return in_array($type, $this->getSupportedTypes(), true);
    }

    /**
     * Get the default WordPress handler for a request type
     */
    public function getDefaultHandler(string $type): ?callable
    {
        return match ($type) {
            'robots' => $this->getRobotsHandler(),
            'favicon' => $this->getFaviconHandler(),
            'feed' => $this->getFeedHandler(),
            'trackback' => $this->getTrackbackHandler(),
            'xmlrpc' => $this->getXmlRpcHandler(),
            'pingback' => $this->getPingbackHandler(),
            default => null
        };
    }

    /**
     * Set explicit routes for special requests
     */
    public function setExplicitRoutes(array $routes): void
    {
        $this->explicitRoutes = array_merge($this->explicitRoutes, $routes);
    }

    /**
     * Add an explicit route for a special request type
     */
    public function addExplicitRoute(string $type, Route $route): void
    {
        $this->explicitRoutes[$type] = $route;
    }

    /**
     * Remove an explicit route for a special request type
     */
    public function removeExplicitRoute(string $type): bool
    {
        if (isset($this->explicitRoutes[$type])) {
            unset($this->explicitRoutes[$type]);
            return true;
        }

        return false;
    }

    /**
     * Get handler information for debugging
     */
    public function getHandlerInfo(): array
    {
        return [
            'supported_types' => $this->getSupportedTypes(),
            'custom_handlers' => array_keys($this->customHandlers),
            'explicit_routes' => array_keys($this->explicitRoutes),
            'wordpress_handlers' => $this->getWordPressHandlerStatus(),
        ];
    }

    /**
     * Check if WordPress has a default handler for the type
     */
    private function hasWordPressDefaultHandler(string $type): bool
    {
        return match ($type) {
            'robots' => function_exists('do_robots'),
            'favicon' => function_exists('do_favicon'),
            'feed' => function_exists('do_feed'),
            'trackback' => function_exists('do_trackback'),
            'xmlrpc' => function_exists('xmlrpc_server'),
            'pingback' => function_exists('xmlrpc_pingback_error'),
            default => false
        };
    }

    /**
     * Execute custom handler
     */
    private function executeCustomHandler(string $type, SpecialRequest $request): mixed
    {
        try {
            $handler = $this->customHandlers[$type];
            return $handler($request);
        } catch (\Throwable $e) {
            // Log error and fallback to WordPress default if available
            if ($this->hasWordPressDefaultHandler($type)) {
                return $this->executeWordPressDefault($type, $request);
            }

            throw $e;
        }
    }

    /**
     * Execute WordPress default handler
     */
    private function executeWordPressDefault(string $type, SpecialRequest $request): mixed
    {
        return match ($type) {
            'robots' => $this->handleRobots($request),
            'favicon' => $this->handleFavicon($request),
            'feed' => $this->handleFeed($request),
            'trackback' => $this->handleTrackback($request),
            'xmlrpc' => $this->handleXmlRpc($request),
            'pingback' => $this->handlePingback($request),
            default => null
        };
    }

    /**
     * Handle robots.txt request
     */
    private function handleRobots(SpecialRequest $request): mixed
    {
        if (function_exists('do_robots')) {
            // Set content type
            if (!headers_sent()) {
                header('Content-Type: text/plain; charset=utf-8');
            }

            do_robots();
            return null;
        }

        // Fallback robots.txt content
        $robotsContent = "User-agent: *\nDisallow: /wp-admin/\n";

        if (function_exists('apply_filters')) {
            $robotsContent = apply_filters('robots_txt', $robotsContent, true);
        }

        return response($robotsContent, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Handle favicon.ico request
     */
    private function handleFavicon(SpecialRequest $request): mixed
    {
        if (function_exists('do_favicon')) {
            do_favicon();
            return null;
        }

        // Return 404 for favicon if no WordPress handler
        return response('', 404);
    }

    /**
     * Handle feed request
     */
    private function handleFeed(SpecialRequest $request): mixed
    {
        if (function_exists('do_feed')) {
            do_feed();
            return null;
        }

        return response('Feed not available', 404);
    }

    /**
     * Handle trackback request
     */
    private function handleTrackback(SpecialRequest $request): mixed
    {
        if (function_exists('do_trackback')) {
            do_trackback();
            return null;
        }

        return response('Trackback not available', 404);
    }

    /**
     * Handle XML-RPC request
     */
    private function handleXmlRpc(SpecialRequest $request): mixed
    {
        if (function_exists('xmlrpc_server')) {
            // XML-RPC is handled by WordPress core
            include(ABSPATH . 'xmlrpc.php');
            return null;
        }

        return response('XML-RPC services are disabled on this site.', 403);
    }

    /**
     * Handle pingback request
     */
    private function handlePingback(SpecialRequest $request): mixed
    {
        if (function_exists('xmlrpc_pingback_error')) {
            // Pingback handling
            return null;
        }

        return response('Pingback not available', 404);
    }

    /**
     * Get robots handler function
     */
    private function getRobotsHandler(): ?callable
    {
        return function_exists('do_robots') ? 'do_robots' : null;
    }

    /**
     * Get favicon handler function
     */
    private function getFaviconHandler(): ?callable
    {
        return function_exists('do_favicon') ? 'do_favicon' : null;
    }

    /**
     * Get feed handler function
     */
    private function getFeedHandler(): ?callable
    {
        return function_exists('do_feed') ? 'do_feed' : null;
    }

    /**
     * Get trackback handler function
     */
    private function getTrackbackHandler(): ?callable
    {
        return function_exists('do_trackback') ? 'do_trackback' : null;
    }

    /**
     * Get XML-RPC handler function
     */
    private function getXmlRpcHandler(): ?callable
    {
        return function_exists('xmlrpc_server') ? 'xmlrpc_server' : null;
    }

    /**
     * Get pingback handler function
     */
    private function getPingbackHandler(): ?callable
    {
        return function_exists('xmlrpc_pingback_error') ? 'xmlrpc_pingback_error' : null;
    }

    /**
     * Get WordPress handler status for debugging
     */
    private function getWordPressHandlerStatus(): array
    {
        return [
            'robots' => function_exists('do_robots'),
            'favicon' => function_exists('do_favicon'),
            'feed' => function_exists('do_feed'),
            'trackback' => function_exists('do_trackback'),
            'xmlrpc' => function_exists('xmlrpc_server'),
            'pingback' => function_exists('xmlrpc_pingback_error'),
        ];
    }

    /**
     * Load configuration
     */
    private function loadConfiguration(array $config): void
    {
        if (isset($config['custom_handlers'])) {
            foreach ($config['custom_handlers'] as $type => $handler) {
                if (is_callable($handler)) {
                    $this->registerHandler($type, $handler);
                }
            }
        }

        if (isset($config['explicit_routes'])) {
            $this->setExplicitRoutes($config['explicit_routes']);
        }
    }
}

<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router as IlluminateRouter;
use Pollora\Route\Infrastructure\Models\Route;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressConditionManagerInterface;

/**
 * Extended Laravel Router with WordPress Route model support.
 *
 * This router extends Laravel's default router to create custom Route instances
 * that support WordPress conditional tags. All WordPress-specific business logic
 * (condition resolution, type binding) is delegated to WordPressRoutingService.
 */
class ExtendedRouter extends IlluminateRouter
{
    public function __construct(
        Dispatcher $events,
        ?Container $container = null,
        private readonly ?WordPressConditionManagerInterface $conditionManager = null,
    ) {
        parent::__construct($events, $container);
    }

    /**
     * Create a new Route object with condition resolver injection.
     *
     * Overrides Laravel's route creation to return our domain Route model
     * which supports WordPress conditions.
     *
     * @param  array<string>|string  $methods  HTTP methods for the route
     * @param  string  $uri  Route URI pattern
     * @param  mixed  $action  Route action (controller, closure, etc.)
     * @return Route The configured route instance with condition resolver
     */
    public function newRoute($methods, $uri, $action): Route
    {
        $route = (new Route($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container);

        if ($this->conditionManager instanceof WordPressConditionManagerInterface) {
            $route->setConditionResolver($this->conditionManager);
        }

        return $route;
    }
}

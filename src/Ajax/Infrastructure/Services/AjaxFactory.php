<?php

declare(strict_types=1);

namespace Pollora\Ajax\Infrastructure\Services;

use Pollora\Ajax\Application\Services\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Models\AjaxAction;

/**
 * Factory for creating AjaxAction instances and registering them via the application service.
 */
class AjaxFactory
{
    /**
     * AjaxFactory constructor.
     */
    public function __construct(private readonly RegisterAjaxActionService $registerService) {}

    /**
     * Create a new AjaxAction instance for the given action and callback.
     */
    public function listen(string $action, callable|string $callback): AjaxAction
    {
        return new AjaxAction($action, $callback, $this->registerService);
    }
}

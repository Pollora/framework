<?php

declare(strict_types=1);

namespace Pollora\Ajax\Infrastructure\Services;

use Pollora\Ajax\Domain\Models\AjaxAction;
use Pollora\Ajax\Application\Services\RegisterAjaxActionService;

/**
 * Factory for creating AjaxAction instances and registering them via the application service.
 */
class AjaxFactory
{
    /**
     * @var RegisterAjaxActionService
     */
    private RegisterAjaxActionService $registerService;

    /**
     * AjaxFactory constructor.
     *
     * @param RegisterAjaxActionService $registerService
     */
    public function __construct(RegisterAjaxActionService $registerService) {
        $this->registerService = $registerService;
    }

    /**
     * Create a new AjaxAction instance for the given action and callback.
     *
     * @param string $action
     * @param callable|string $callback
     * @return AjaxAction
     */
    public function listen(string $action, callable|string $callback): AjaxAction
    {
        return new AjaxAction($action, $callback, $this->registerService);
    }
}

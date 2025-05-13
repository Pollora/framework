<?php

declare(strict_types=1);

namespace Pollora\Ajax\Infrastructure\Repositories;

use Pollora\Ajax\Domain\Contracts\AjaxActionRegistrarInterface;
use Pollora\Ajax\Domain\Models\AjaxAction;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;

/**
 * Infrastructure adapter for registering AjaxActions using WordPress hooks.
 * Implements the domain port for AJAX action registration.
 */
class WordPressAjaxActionRegistrar implements AjaxActionRegistrarInterface
{
    protected ServiceLocator $locator;

    protected Action $action;

    public function __construct(ServiceLocator $locator)
    {
        $this->locator = $locator;
        $this->action = $locator->resolve(Action::class);
    }

    /**
     * Register the given AjaxAction using WordPress hooks.
     */
    public function register(AjaxAction $action): void
    {
        if ($action->isBothOrLoggedUsers()) {
            $this->action->add('wp_ajax_'.$action->getName(), $action->getCallback());
        }
        if ($action->isBothOrGuestUsers()) {
            $this->action->add('wp_ajax_nopriv_'.$action->getName(), $action->getCallback());
        }
    }
}

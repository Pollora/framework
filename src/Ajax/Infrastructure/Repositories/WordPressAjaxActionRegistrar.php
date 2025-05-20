<?php

declare(strict_types=1);

namespace Pollora\Ajax\Infrastructure\Repositories;

use Pollora\Ajax\Domain\Contracts\AjaxActionRegistrarInterface;
use Pollora\Ajax\Domain\Models\AjaxAction;
use Pollora\Hook\Infrastructure\Services\Action;
use Psr\Container\ContainerInterface;

/**
 * Infrastructure adapter for registering AjaxActions using WordPress hooks.
 * Implements the domain port for AJAX action registration.
 */
class WordPressAjaxActionRegistrar implements AjaxActionRegistrarInterface
{

    protected Action $action;

    public function __construct(protected ContainerInterface $app)
    {
        $this->action = $this->app->get(Action::class);
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

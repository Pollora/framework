<?php

declare(strict_types=1);

namespace Pollora\Ajax\Infrastructure\Repositories;

use Pollora\Ajax\Domain\Contracts\AjaxActionRegistrarInterface;
use Pollora\Ajax\Domain\Models\AjaxAction;
use Pollora\Support\Facades\Action;

/**
 * Infrastructure adapter for registering AjaxActions using WordPress hooks.
 * Implements the domain port for AJAX action registration.
 */
class WordPressAjaxActionRegistrar implements AjaxActionRegistrarInterface
{
    /**
     * Register the given AjaxAction using WordPress hooks.
     *
     * @param AjaxAction $action
     * @return void
     */
    public function register(AjaxAction $action): void
    {
        if ($action->isBothOrLoggedUsers()) {
            Action::add('wp_ajax_' . $action->getName(), $action->getCallback());
        }
        if ($action->isBothOrGuestUsers()) {
            Action::add('wp_ajax_nopriv_' . $action->getName(), $action->getCallback());
        }
    }
}

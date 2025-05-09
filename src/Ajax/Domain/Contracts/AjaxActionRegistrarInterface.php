<?php

declare(strict_types=1);

namespace Pollora\Ajax\Domain\Contracts;

use Pollora\Ajax\Domain\Models\AjaxAction;

/**
 * Port interface for registering an AjaxAction in the system.
 */
interface AjaxActionRegistrarInterface
{
    /**
     * Register the given AjaxAction.
     *
     * @param AjaxAction $action
     * @return void
     */
    public function register(AjaxAction $action): void;
}

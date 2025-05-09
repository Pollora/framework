<?php

declare(strict_types=1);

namespace Pollora\Ajax\Application\Services;

use Pollora\Ajax\Domain\Contracts\AjaxActionRegistrarInterface;
use Pollora\Ajax\Domain\Models\AjaxAction;

/**
 * Application service to orchestrate the registration of an AjaxAction via the domain port.
 */
class RegisterAjaxActionService
{
    /**
     * @var AjaxActionRegistrarInterface
     */
    private AjaxActionRegistrarInterface $registrar;

    /**
     * RegisterAjaxActionService constructor.
     *
     * @param AjaxActionRegistrarInterface $registrar
     */
    public function __construct(AjaxActionRegistrarInterface $registrar) {
        $this->registrar = $registrar;
    }

    /**
     * Register the given AjaxAction using the domain port.
     *
     * @param AjaxAction $action
     * @return void
     */
    public function execute(AjaxAction $action): void
    {
        $this->registrar->register($action);
    }
}

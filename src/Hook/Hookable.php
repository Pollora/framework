<?php

declare(strict_types=1);

namespace Pollen\Hook;

use Pollen\Foundation\Application;

/**
 * Hookable class
 *
 * @author Julien Lambé <julien@themosis.com>
 */
class Hookable
{
    protected Application $app;

    /**
     * @var string|array
     */
    public $hook;

    public int $priority = 10;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }
}

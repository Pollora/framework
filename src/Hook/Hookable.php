<?php

declare(strict_types=1);

namespace Pollen\Hook;

use Illuminate\Contracts\Foundation\Application;

/**
 * Hookable class
 *
 * @author Julien Lambé <julien@themosis.com>
 */
class Hookable
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var string|array
     */
    public $hook;

    /**
     * @var int
     */
    public $priority = 10;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }
}

<?php

declare(strict_types=1);

namespace Pollen\Foundation;

use Application\Hooks\HooksRepository;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Log\LogServiceProvider;
use Illuminate\Support\Collection;
use Pollen\Route\RouteServiceProvider;

class Application extends \Illuminate\Foundation\Application
{
    public function registerBaseServiceProviders()
    {
        $this->register(new EventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new RouteServiceProvider($this));
    }

    /**
     * Register a list of hookable instances.
     */
    public function registerConfiguredHooks(string $config = '')
    {
        if (empty($config)) {
            $config = 'app.hooks';
        }

        $hooks = Collection::make($this->config[$config]);

        (new HooksRepository($this))->load($hooks->all());
    }
}

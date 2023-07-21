<?php

namespace Pollen\Foundation;

use Illuminate\Events\EventServiceProvider;
use Illuminate\Log\LogServiceProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Pollen\Route\RouteServiceProvider;
use Application\Hooks\HooksRepository;

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

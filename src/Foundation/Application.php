<?php

declare(strict_types=1);

namespace Pollen\Foundation;

use Illuminate\Events\EventServiceProvider;
use Illuminate\Log\LogServiceProvider;
use Pollen\Route\RouteServiceProvider;

class Application extends \Illuminate\Foundation\Application
{
    public function registerBaseServiceProviders()
    {
        $this->register(new EventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new RouteServiceProvider($this));
    }
}

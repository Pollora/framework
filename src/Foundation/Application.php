<?php

declare(strict_types=1);

namespace Pollora\Foundation;

use Illuminate\Events\EventServiceProvider;
use Illuminate\Log\LogServiceProvider;
use Pollora\Route\RouteServiceProvider;

class Application extends \Illuminate\Foundation\Application
{
    public function registerBaseServiceProviders(): void
    {
        $this->register(new EventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new RouteServiceProvider($this));
    }
}

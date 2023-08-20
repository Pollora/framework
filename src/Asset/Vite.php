<?php

declare(strict_types=1);

namespace Pollen\Asset;

use Illuminate\Support\HtmlString;
use Pollen\Foundation\Application;

class Vite
{
    protected ?HtmlString $client = null;

    protected bool $viteLoaded = false;

    public function __construct(Application $app)
    {
        $this->client = $app->get('Illuminate\Foundation\Vite')([]);
    }

    public function loadVite()
    {
        $this->viteLoaded = true;
    }

    public function viteLoaded()
    {
        return $this->viteLoaded;
    }
}

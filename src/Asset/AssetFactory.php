<?php

declare(strict_types=1);

namespace Pollen\Asset;

use Pollen\Foundation\Application;

class AssetFactory
{
    protected Application $app;

    public function add($handle, $file)
    {
        return new Asset($handle, $file);
    }
}

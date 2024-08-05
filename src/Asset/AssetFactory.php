<?php

declare(strict_types=1);

namespace Pollen\Asset;


class AssetFactory
{
    public function add(string $handle, string $file)
    {
        return new Asset($handle, $file);
    }
}

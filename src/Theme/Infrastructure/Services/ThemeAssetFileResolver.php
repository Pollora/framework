<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Pollora\Asset\Infrastructure\Services\AssetFile;
use Pollora\Theme\Domain\Contracts\ThemeAssetUrlResolverInterface;

/**
 * Answers the built URL of a theme file through the `theme` asset container.
 */
class ThemeAssetFileResolver implements ThemeAssetUrlResolverInterface
{
    public function assetUrl(string $file): string
    {
        return (string) (new AssetFile($file))->from('theme');
    }
}

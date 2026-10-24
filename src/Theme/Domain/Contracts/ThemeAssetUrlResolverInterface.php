<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Contracts;

/**
 * Answers the URL the theme's asset build gave a file.
 */
interface ThemeAssetUrlResolverInterface
{
    /**
     * The URL of a file in the theme's asset container.
     *
     * @param  string  $file  The file, relative to the asset container root
     * @return string The built URL, or an empty string when the build does not know the file
     */
    public function assetUrl(string $file): string;
}

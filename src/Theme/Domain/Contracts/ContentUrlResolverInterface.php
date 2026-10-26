<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Contracts;

/**
 * Answers the public URL of the content directory.
 */
interface ContentUrlResolverInterface
{
    /**
     * The URL of the content directory, or of a path below it.
     *
     * @param  string  $path  A path relative to the content directory
     */
    public function contentUrl(string $path = ''): string;
}

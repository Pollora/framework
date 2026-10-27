<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Pollora\Theme\Domain\Contracts\ContentUrlResolverInterface;

/**
 * Answers the content URL through WordPress's content_url().
 */
class WordPressContentUrlResolver implements ContentUrlResolverInterface
{
    public function contentUrl(string $path = ''): string
    {
        return \content_url($path);
    }
}

<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Infrastructure\Registrars;

use Pollora\BlockPattern\Domain\Contracts\PatternRegistrarInterface;
use Pollora\BlockPattern\Domain\Models\Pattern;

/**
 * WordPress implementation of PatternRegistrarInterface.
 *
 * This is an adapter in hexagonal architecture that connects
 * our domain to WordPress for pattern registration.
 */
class WordPressPatternRegistrar implements PatternRegistrarInterface
{
    /**
     * {@inheritdoc}
     */
    public function registerPattern(Pattern $pattern): void
    {
        if (!function_exists('register_block_pattern')) {
            return;
        }

        \register_block_pattern($pattern->getSlug(), $pattern->toArray());
    }
} 
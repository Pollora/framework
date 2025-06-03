<?php

declare(strict_types=1);

namespace Pollora\BlockCategory\Infrastructure\Registrars;

use Pollora\BlockCategory\Domain\Contracts\BlockCategoryRegistrarInterface;
use Pollora\Collection\Domain\Contracts\CollectionInterface;

/**
 * WordPress implementation of BlockCategoryRegistrarInterface.
 *
 * This is an adapter in hexagonal architecture that connects
 * our domain to WordPress for block category registration.
 */
class BlockCategoryRegistrar implements BlockCategoryRegistrarInterface
{
    /**
     * {@inheritdoc}
     */
    public function registerCategories(CollectionInterface $categories): void
    {
        if ($categories->isEmpty()) {
            return;
        }

        if (function_exists('add_filter')) {
            \add_filter('block_categories_all', function (array $existingCategories) use ($categories): array {
                return array_merge(
                    $existingCategories,
                    $categories->values()->all()
                );
            });
        }
    }
}

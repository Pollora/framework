<?php

declare(strict_types=1);

namespace Pollora\BlockCategory\Application\Services;

use Pollora\BlockCategory\Domain\Contracts\BlockCategoryRegistrarInterface;
use Pollora\BlockCategory\Domain\Contracts\BlockCategoryServiceInterface;
use Pollora\BlockCategory\Domain\Models\BlockCategory;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;

/**
 * Application service for block category use cases.
 *
 * This service orchestrates the domain logic and coordinates
 * between the configuration, collection, and registration infrastructure.
 */
class BlockCategoryService implements BlockCategoryServiceInterface
{
    /**
     * Create a new block category service instance.
     */
    public function __construct(
        private ConfigRepositoryInterface $config,
        private CollectionFactoryInterface $collectionFactory,
        private BlockCategoryRegistrarInterface $registrar
    ) {}

    /**
     * {@inheritdoc}
     */
    public function registerConfiguredCategories(): void
    {
        $configData = $this->config->get('theme.gutenberg.categories.blocks', []);

        if (empty($configData)) {
            return;
        }

        $configuredCategories = $this->collectionFactory->make($configData);

        if ($configuredCategories->isEmpty()) {
            return;
        }

        // Transform data into proper format for registration
        $formattedCategories = $configuredCategories->map(function (array $args, string $slug): array {
            $blockCategory = new BlockCategory(
                $slug,
                $args['label'] ?? $args['title'] ?? ''
            );

            return $blockCategory->toArray();
        });

        $this->registrar->registerCategories($formattedCategories);
    }
}

<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Registrars;

use Pollora\Gutenberg\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Gutenberg\Domain\Contracts\ConfigRepositoryInterface;

/**
 * Registrar for Gutenberg block categories.
 *
 * Handles the registration of custom block categories
 * defined in the theme configuration.
 */
class BlockCategoryRegistrar
{
    private ConfigRepositoryInterface $config;

    private CollectionFactoryInterface $collectionFactory;

    /**
     * BlockCategoryRegistrar constructor.
     */
    public function __construct(
        ConfigRepositoryInterface $config,
        CollectionFactoryInterface $collectionFactory
    ) {
        $this->config = $config;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Register configured block categories.
     *
     * Reads categories from theme configuration and registers them
     * in WordPress through the 'block_categories_all' filter.
     */
    public function register(): void
    {
        /** @var \Illuminate\Support\Collection<string, array<string, mixed>> $configuredCategories */
        $configuredCategories = $this->collectionFactory->make(
            $this->config->get('theme.gutenberg.categories.blocks', [])
        );

        add_filter('block_categories_all', fn (array $categories): array => array_merge(
            $categories,
            $configuredCategories->map(fn (array $args, string $slug): array => [
                'slug' => $slug,
                'title' => $args['label'] ?? $args['title'] ?? '',
            ])->values()->all()
        ));
    }
}

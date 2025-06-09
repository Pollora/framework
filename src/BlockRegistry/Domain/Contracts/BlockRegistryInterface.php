<?php

declare(strict_types=1);

namespace Pollora\BlockRegistry\Domain\Contracts;

use Pollora\BlockRegistry\Domain\Exception\BlockRegistryException;
use WP_Block_Type;

interface BlockRegistryInterface
{
    /**
     * @throws BlockRegistryException
     */
    public function registerBlockType(string|WP_Block_Type $blockType, array $args = []): ?WP_Block_Type;

    /**
     * Registers all block types from a block metadata collection.
     *
     * @param  string  $path  The absolute base path for the collection (e.g., WP_PLUGIN_DIR . '/my-plugin/blocks/').
     * @param  string  $manifest  The path to the manifest file for the collection (optional).
     *
     * @throws BlockRegistryException
     */
    public function registerBlockTypesFromMetadataCollection(string $path, string $manifest = ''): bool;

    /**
     * Registers a block metadata collection.
     *
     * @param  string  $path  The base path in which block files for the collection reside.
     * @param  string  $manifest  The path to the manifest file for the collection.
     *
     * @throws BlockRegistryException
     */
    public function registerBlockMetadataCollection(string $path, string $manifest): bool;

    /**
     * Register a collection of blocks from a WordPress scripts compiled manifest file.
     *
     * @param  string  $manifest  The path to the manifest file for the collection.
     */
    public function registerBlockCollectionFromManifest(string $manifest): bool;
}

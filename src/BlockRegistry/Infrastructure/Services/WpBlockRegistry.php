<?php

declare(strict_types=1);

namespace Pollora\BlockRegistry\Infrastructure\Services;

use Pollora\BlockRegistry\Domain\Contracts\BlockRegistryInterface;
use Pollora\BlockRegistry\Domain\Exception\BlockRegistryException;
use WP_Block_Type;

use function register_block_type;
use function wp_register_block_types_from_metadata_collection;

class WpBlockRegistry implements BlockRegistryInterface
{
    /** {@inheritDoc} **/
    public function registerBlockType(string|WP_Block_Type $blockType, array $args = []): ?WP_Block_Type
    {
        return register_block_type($blockType, $args) ?: null;
    }

    /** {@inheritDoc} *
     */
    public function registerBlockMetadataCollection(string $path, string $manifest): bool
    {
        $this->validateBlockCollection($path, $manifest);

        if (! function_exists('wp_register_block_metadata_collection')) {
            throw new BlockRegistryException('The function wp_register_block_metadata_collection does not exist.');
        }
        wp_register_block_metadata_collection($path, $manifest);

        return true;
    }

    /** {@inheritDoc} *
     */
    public function registerBlockTypesFromMetadataCollection(string $path, string $manifest = ''): bool
    {
        if (! function_exists('wp_register_block_types_from_metadata_collection')) {
            throw new BlockRegistryException('The function wp_register_block_types_from_metadata_collection does not exist.');
        }
        $this->validateBlockCollection($path, $manifest);

        wp_register_block_types_from_metadata_collection($path, $manifest);

        return true;
    }

    /**
     * Validates the block collection path and manifest file.
     *
     * @param  string  $path  The absolute base path for the collection.
     * @param  string  $manifest  The path to the manifest file for the collection (optional).
     *
     * @throws BlockRegistryException If the directory or manifest file does not exist.
     */
    protected function validateBlockCollection(string $path, string $manifest = ''): void
    {
        if (! is_dir($path)) {
            throw new BlockRegistryException("The block collection directory does not exist: $path");
        }
        if ($manifest !== '' && ! is_file($manifest)) {
            throw new BlockRegistryException("The manifest file does not exist: $manifest");
        }
    }

    public function registerBlockCollectionFromManifest(string $manifest): bool
    {
        $basePath = dirname($manifest);

        if (function_exists('wp_register_block_types_from_metadata_collection')) {
            return $this->registerBlockTypesFromMetadataCollection($basePath, $manifest);

        }

        $this->registerBlockMetadataCollection($basePath, $manifest);

        $manifestData = require $manifest;
        foreach (array_keys($manifestData) as $blockType) {
            $blockPath = $basePath.DIRECTORY_SEPARATOR.$blockType;
            $this->registerBlockType($blockPath);
        }

        return true;
    }
}

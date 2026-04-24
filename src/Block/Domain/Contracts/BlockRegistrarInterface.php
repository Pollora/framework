<?php

declare(strict_types=1);

namespace Pollora\Block\Domain\Contracts;

/**
 * Contract for scanning and registering Gutenberg blocks built with Vite.
 */
interface BlockRegistrarInterface
{
    /**
     * Scan a directory for subdirectories containing block.json and register each block.
     *
     * @param  string  $directory  Absolute path to the blocks directory
     * @param  string  $containerName  Asset container name (e.g. 'theme' or 'plugin.{slug}')
     */
    public function registerDirectory(string $directory, string $containerName): void;

    /**
     * Register a single block from its directory.
     *
     * @param  string  $blockDir  Absolute path to the block directory containing block.json
     * @param  string  $containerName  Asset container name
     */
    public function registerBlock(string $blockDir, string $containerName): void;
}

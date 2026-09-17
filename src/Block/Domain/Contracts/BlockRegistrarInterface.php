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
     * A theme or plugin `resources/views/blocks` directory also scans the deprecated
     * `resources/blocks` next to it, and the other way round.
     *
     * @param  string  $directory  Absolute path to the blocks directory
     * @param  string  $containerName  Asset container name (e.g. 'theme' or 'plugin.{slug}')
     * @param  string|null  $basePath  Vite project root; detected from the closest vite.config file when null
     */
    public function registerDirectory(string $directory, string $containerName, ?string $basePath = null): void;

    /**
     * Register a single block from its directory.
     *
     * @param  string  $blockDir  Absolute path to the block directory containing block.json
     * @param  string  $containerName  Asset container name
     * @param  string|null  $basePath  Vite project root; detected from the closest vite.config file when null
     */
    public function registerBlock(string $blockDir, string $containerName, ?string $basePath = null): void;
}

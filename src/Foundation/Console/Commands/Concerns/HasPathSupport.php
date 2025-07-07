<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console\Commands\Concerns;

use Symfony\Component\Console\Input\InputOption;

trait HasPathSupport
{
    /**
     * Get the console command options for path support.
     *
     * @return array The path-related command options
     */
    protected function getPathOptions(): array
    {
        return [
            ['path', null, InputOption::VALUE_OPTIONAL, 'The custom path to generate the class in'],
        ];
    }

    /**
     * Get the custom path from option.
     *
     * @return string|null The custom path if specified
     */
    protected function getPathOption(): ?string
    {
        return $this->option('path');
    }

    /**
     * Check if custom path option is specified.
     *
     * @return bool True if path option is set
     */
    protected function hasPathOption(): bool
    {
        return $this->option('path') !== null;
    }

    /**
     * Resolve custom path.
     *
     * @return string|null The resolved custom path
     */
    protected function resolvePath(): ?string
    {
        return $this->getPathOption();
    }

    /**
     * Resolve custom path location.
     *
     * @return array{type: string, path: string, namespace: string}
     *
     * @throws InvalidArgumentException When custom path is empty
     */
    protected function resolveCustomPath(): array
    {
        $path = $this->resolvePath();

        if (! $path) {
            throw new InvalidArgumentException('Custom path cannot be empty when --path option is used.');
        }

        // Ensure absolute path
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        return [
            'type' => 'custom',
            'path' => $path,
            'namespace' => 'App', // Default namespace for custom paths
        ];
    }
}

<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Infrastructure\Services\Scouts;

use Illuminate\Contracts\Container\Container;
use Pollora\Attributes\Attributable;
use Pollora\Discoverer\Infrastructure\Services\SpatieDiscoveryAdapter;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering classes with attributes.
 */
final class AttributeScout extends SpatieDiscoveryAdapter
{
    /**
     * Directories to scan for attributable classes.
     *
     * @var array<string>
     */
    private array $directories = [];

    /**
     * Constructor.
     *
     * @param  Container  $app  The Laravel application container
     * @param  array<string>  $directories  Directories to scan for attributable classes
     */
    public function __construct(
        Container $app,
        array $directories = []
    ) {
        parent::__construct($app);
        $this->directories = $directories;
    }

    /**
     * Get default directories for attribute discovery.
     * This can be called from the service provider.
     *
     * @param  string  $appPath  Application path
     * @param  string  $modulesPath  Modules path
     * @return array<string>
     */
    public static function getDefaultDirectories(string $appPath = '', string $modulesPath = ''): array
    {
        $directories = [];

        if ($appPath) {
            $directories[] = $appPath;
        }

        if ($modulesPath) {
            $directories[] = $modulesPath;
        }

        return $directories;
    }

    public function getDirectories(): array
    {
        return $this->directories;
    }

    public function getType(): string
    {
        return 'attribute';
    }

    protected function criteria(Discover $discover): Discover
    {
        return $discover->implementing(Attributable::class);
    }
}

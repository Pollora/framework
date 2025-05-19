<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Infrastructure\Services\Scouts;

use Illuminate\Contracts\Container\Container;
use Pollora\Discoverer\Infrastructure\Services\SpatieDiscoveryAdapter;
use Pollora\PostType\AbstractPostType;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering post types.
 */
final class PostTypeScout extends SpatieDiscoveryAdapter
{
    /**
     * Directories to scan for post types.
     *
     * @var array<string>
     */
    private array $directories = [];

    /**
     * Constructor.
     *
     * @param  Container  $app  The Laravel application container
     * @param  array<string>  $directories  Directories to scan for post types
     */
    public function __construct(
        Container $app,
        array $directories = []
    ) {
        parent::__construct($app);
        $this->directories = $directories;
    }

    /**
     * Get default directories for post type discovery.
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
        return 'post_type';
    }

    protected function criteria(Discover $discover): Discover
    {
        return $discover->extending(AbstractPostType::class);
    }
}

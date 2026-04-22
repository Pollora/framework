<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;

/**
 * Generic module manifest service inspired by nwidart/laravel-modules.
 *
 * This service is now simplified and only handles Laravel Module (nwidart) specific tasks.
 * Regular themes and plugins now use the self-registration system.
 */
class ModuleManifest
{
    protected Collection $paths;

    protected array $manifest = [];

    protected static ?Collection $manifestData = null;

    public function __construct(
        protected Filesystem $files,
        array $paths,
        protected ?string $manifestPath,
        protected ModuleRepositoryInterface $repository,
        protected ?object $scout = null // Legacy parameter, unused
    ) {
        $this->paths = collect($paths);
    }

    /**
     * Reset cached manifest data.
     */
    public static function resetCache(): void
    {
        self::$manifestData = null;
    }
}

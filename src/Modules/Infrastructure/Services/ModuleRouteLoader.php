<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Route;
use Pollora\Modules\Domain\Contracts\ModuleInterface;
use Psr\Log\LoggerInterface;

/**
 * Generic module route loader.
 *
 * Automatically loads route files (api.php, web.php) from a module's
 * routes/ directory and registers them with the Laravel router.
 *
 * - api.php routes are prefixed with /api and use no state middleware
 * - web.php routes use the default web middleware group
 *
 * This service is used by both ThemeRegistrar and PluginRegistrar to
 * provide consistent route loading across all module types.
 */
class ModuleRouteLoader
{
    private ?LoggerInterface $logger = null;

    public function __construct(
        protected Container $app
    ) {
        try {
            $this->logger = $app->make(LoggerInterface::class);
        } catch (\Throwable) {
        }
    }

    /**
     * Load all route files for a module.
     */
    public function loadModuleRoutes(ModuleInterface $module): void
    {
        $routesPath = $module->getPath().'/routes';

        if (! is_dir($routesPath)) {
            return;
        }

        $this->loadApiRoutes($routesPath);
        $this->loadWebRoutes($routesPath);
    }

    /**
     * Load API routes (routes/api.php).
     *
     * API routes are prefixed with /api and have no session/CSRF middleware,
     * making them ideal for lightweight JSON endpoints.
     */
    protected function loadApiRoutes(string $routesPath): void
    {
        $apiFile = $routesPath.'/api.php';

        if (! file_exists($apiFile)) {
            return;
        }

        try {
            Route::prefix('api')
                ->group($apiFile);
        } catch (\Throwable $throwable) {
            $this->logger?->error('Failed to load API routes: '.$throwable->getMessage(), [
                'file' => $apiFile,
                'exception' => $throwable,
            ]);
        }
    }

    /**
     * Load web routes (routes/web.php).
     *
     * Web routes use the default middleware stack and have no prefix.
     */
    protected function loadWebRoutes(string $routesPath): void
    {
        $webFile = $routesPath.'/web.php';

        if (! file_exists($webFile)) {
            return;
        }

        try {
            Route::group([], $webFile);
        } catch (\Throwable $throwable) {
            $this->logger?->error('Failed to load web routes: '.$throwable->getMessage(), [
                'file' => $webFile,
                'exception' => $throwable,
            ]);
        }
    }
}

<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Pollora\Modules\Domain\Contracts\ModuleInterface;
use Pollora\Modules\Infrastructure\Services\ModuleRouteLoader;

function createMockModuleForRouteLoader(string $path): ModuleInterface
{
    $module = Mockery::mock(ModuleInterface::class);
    $module->shouldReceive('getPath')->andReturn($path);
    $module->shouldReceive('getName')->andReturn('test-module');

    return $module;
}

describe('ModuleRouteLoader', function (): void {
    beforeEach(function (): void {
        $this->app = new Container;
        $this->loader = new ModuleRouteLoader($this->app);

        // Ensure facades can create Mockery mocks via shouldReceive()
        // by clearing any stale application reference from previous tests.
        Facade::setFacadeApplication(null);
    });

    it('does nothing when routes directory does not exist', function (): void {
        $module = createMockModuleForRouteLoader('/non/existent/path');

        Route::shouldReceive('prefix')->never();
        Route::shouldReceive('group')->never();

        $this->loader->loadModuleRoutes($module);
    });

    it('loads api.php when it exists', function (): void {
        $tempDir = sys_get_temp_dir().'/test_routes_api_'.uniqid();
        mkdir($tempDir.'/routes', 0777, true);
        file_put_contents($tempDir.'/routes/api.php', '<?php // api routes');

        $module = createMockModuleForRouteLoader($tempDir);

        $pendingGroup = Mockery::mock();
        $pendingGroup->shouldReceive('group')
            ->once()
            ->with($tempDir.'/routes/api.php');

        Route::shouldReceive('prefix')
            ->once()
            ->with('api')
            ->andReturn($pendingGroup);

        $this->loader->loadModuleRoutes($module);

        unlink($tempDir.'/routes/api.php');
        rmdir($tempDir.'/routes');
        rmdir($tempDir);
    });

    it('loads web.php when it exists', function (): void {
        $tempDir = sys_get_temp_dir().'/test_routes_web_'.uniqid();
        mkdir($tempDir.'/routes', 0777, true);
        file_put_contents($tempDir.'/routes/web.php', '<?php // web routes');

        $module = createMockModuleForRouteLoader($tempDir);

        Route::shouldReceive('prefix')->never();

        Route::shouldReceive('group')
            ->once()
            ->with([], $tempDir.'/routes/web.php');

        $this->loader->loadModuleRoutes($module);

        expect(file_exists($tempDir.'/routes/web.php'))->toBeTrue();

        unlink($tempDir.'/routes/web.php');
        rmdir($tempDir.'/routes');
        rmdir($tempDir);
    });

    it('loads both api.php and web.php when both exist', function (): void {
        $tempDir = sys_get_temp_dir().'/test_routes_both_'.uniqid();
        mkdir($tempDir.'/routes', 0777, true);
        file_put_contents($tempDir.'/routes/api.php', '<?php // api');
        file_put_contents($tempDir.'/routes/web.php', '<?php // web');

        $module = createMockModuleForRouteLoader($tempDir);

        $pendingGroup = Mockery::mock();
        $pendingGroup->shouldReceive('group')
            ->once()
            ->with($tempDir.'/routes/api.php');

        Route::shouldReceive('prefix')
            ->once()
            ->with('api')
            ->andReturn($pendingGroup);

        Route::shouldReceive('group')
            ->once()
            ->with([], $tempDir.'/routes/web.php');

        $this->loader->loadModuleRoutes($module);

        unlink($tempDir.'/routes/api.php');
        unlink($tempDir.'/routes/web.php');
        rmdir($tempDir.'/routes');
        rmdir($tempDir);
    });

    it('skips missing route files gracefully', function (): void {
        $tempDir = sys_get_temp_dir().'/test_routes_empty_'.uniqid();
        mkdir($tempDir.'/routes', 0777, true);

        $module = createMockModuleForRouteLoader($tempDir);

        Route::shouldReceive('prefix')->never();
        Route::shouldReceive('group')->never();

        $this->loader->loadModuleRoutes($module);

        expect(true)->toBeTrue(); // explicit assertion: no routes loaded

        rmdir($tempDir.'/routes');
        rmdir($tempDir);
    });
});

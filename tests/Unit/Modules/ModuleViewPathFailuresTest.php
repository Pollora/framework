<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\View\Factory;
use Illuminate\View\ViewFinderInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Psr\Log\LoggerInterface;

/**
 * @param  object  $finder  What the view factory hands out as its finder
 */
function moduleAssetManagerWithFinder(object $finder, ?LoggerInterface $logger = null): ModuleAssetManager
{
    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('getFinder')->andReturn($finder);
    $factory->shouldReceive('addNamespace')->andReturnNull();

    $app = new Container;
    $app->instance('view', $factory);

    if ($logger instanceof LoggerInterface) {
        $app->instance(LoggerInterface::class, $logger);
    }

    return new ModuleAssetManager($app);
}

beforeEach(function (): void {
    $this->module = sys_get_temp_dir().'/pollora-module-failures-'.bin2hex(random_bytes(4));
    mkdir($this->module.'/resources/views', 0755, true);
});

afterEach(function (): void {
    foreach (['/resources/views', '/resources', ''] as $directory) {
        is_dir($this->module.$directory) && rmdir($this->module.$directory);
    }

    Mockery::close();
});

describe('ModuleAssetManager view path failures', function (): void {
    it('registers nothing when the application has no view service', function (): void {
        $manager = new ModuleAssetManager(new Container);

        expect(fn () => $manager->registerModuleViewPaths($this->module, 'plugin', 'demo'))->not->toThrow(Throwable::class);
    });

    it('registers nothing when the view factory hands out something that is not a view finder', function (): void {
        $manager = moduleAssetManagerWithFinder(new stdClass);

        expect(fn () => $manager->registerModuleViewPaths($this->module, 'plugin', 'demo'))->not->toThrow(Throwable::class);
    });

    it('falls back to addLocation when the finder cannot list its paths', function (): void {
        $finder = Mockery::mock(ViewFinderInterface::class);
        $finder->shouldReceive('getPaths')->andThrow(new RuntimeException('no paths'));
        $finder->shouldReceive('addLocation')->once()->with($this->module.'/resources/views');
        $finder->shouldReceive('addNamespace')->andReturnNull();

        moduleAssetManagerWithFinder($finder)->registerModuleViewPaths($this->module, 'plugin', 'demo');
    });

    it('logs the path when even addLocation fails', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(
            'Failed to register view path with priority: '.$this->module.'/resources/views',
            Mockery::on(fn (array $c): bool => $c['exception'] instanceof RuntimeException)
        );
        $finder = Mockery::mock(ViewFinderInterface::class);
        $finder->shouldReceive('getPaths')->andThrow(new RuntimeException('no paths'));
        $finder->shouldReceive('addLocation')->andThrow(new RuntimeException('read only'));
        $finder->shouldReceive('addNamespace')->andReturnNull();

        moduleAssetManagerWithFinder($finder, $logger)->registerModuleViewPaths($this->module, 'plugin', 'demo');
    });

    it('has no Blade directive to register for a module without a directives file', function (): void {
        (new ModuleAssetManager(new Container))->registerModuleBladeDirectives($this->module);

        expect(file_exists($this->module.'/resources/directives.php'))->toBeFalse();
    });
});

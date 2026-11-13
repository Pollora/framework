<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Pollora\BlockPattern\UI\PatternComponent;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Pollora\Modules\Infrastructure\Services\ModuleComponentManager;
use Pollora\Modules\Infrastructure\Services\ModuleConfigurationLoader;
use Pollora\Modules\Infrastructure\Services\ModuleDiscoveryOrchestrator;
use Pollora\Modules\Infrastructure\Services\ModuleRouteLoader;
use Pollora\Theme\Application\Services\ThemeRegistrar;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Models\ImageSize;
use Pollora\Theme\Domain\Models\Menus;
use Pollora\Theme\Domain\Models\Sidebar;
use Pollora\Theme\Domain\Models\Templates;
use Pollora\Theme\Domain\Models\ThemeInitializer;
use Pollora\Theme\Infrastructure\Repositories\ThemeRepository;
use Pollora\Theme\Infrastructure\Services\Support;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;
use Psr\Log\LoggerInterface;

/**
 * What the registrar hands over to each module service when a theme registers itself.
 */
function registrarThemeDir(array $subDirs = []): string
{
    $dir = sys_get_temp_dir().'/pollora-registrar-'.bin2hex(random_bytes(6));
    mkdir($dir);
    foreach ($subDirs as $sub) {
        mkdir($dir.'/'.$sub);
    }

    return $dir;
}

function registrarCleanup(string $dir): void
{
    foreach (glob($dir.'/*') ?: [] as $entry) {
        @rmdir($entry);
    }

    @rmdir($dir);
}

function registrarRun(Container $container, string $dir): ThemeModuleInterface
{
    Functions\when('get_stylesheet')->justReturn('demo');
    Functions\when('get_stylesheet_directory')->justReturn($dir);

    $parser = Mockery::mock(WordPressThemeParser::class);
    $parser->shouldReceive('parseThemeHeaders')->andReturn(['Theme Name' => 'Demo']);

    try {
        return (new ThemeRegistrar($container, $parser))->register();
    } finally {
        registrarCleanup($dir);
    }
}

describe('ThemeRegistrar wiring', function (): void {
    it('lets the theme service load the theme by its stylesheet name', function (): void {
        $container = new Container;
        $service = Mockery::mock(ThemeService::class);
        $service->shouldReceive('load')->once()->with('demo');
        $container->instance(ThemeService::class, $service);

        registrarRun($container, registrarThemeDir());
    });

    it('resets the theme repository cache when a theme registers', function (): void {
        $container = new Container;
        $repository = Mockery::mock(ThemeRepository::class);
        $repository->shouldReceive('resetCache')->once();
        $container->instance(ModuleRepositoryInterface::class, $repository);

        registrarRun($container, registrarThemeDir());
    });

    it('does not touch a repository that is not the theme repository', function (): void {
        $container = new Container;
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $repository->shouldNotReceive('resetCache');

        $container->instance(ModuleRepositoryInterface::class, $repository);

        registrarRun($container, registrarThemeDir());
    });

    it('logs and carries on when resetting the repository cache fails', function (): void {
        $container = new Container;
        $repository = Mockery::mock(ThemeRepository::class);
        $repository->shouldReceive('resetCache')->andThrow(new RuntimeException('nope'));
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with('Failed to invalidate theme repository cache: nope');
        $container->instance(ModuleRepositoryInterface::class, $repository);
        $container->instance(LoggerInterface::class, $logger);

        registrarRun($container, registrarThemeDir());
    });

    it('runs discovery on the theme app directory when it exists', function (): void {
        $dir = registrarThemeDir(['app', 'src']);
        $container = new Container;
        $discovery = Mockery::mock(ModuleDiscoveryOrchestrator::class);
        $discovery->shouldReceive('discover')->once()->with($dir.'/app');
        $container->instance(ModuleDiscoveryOrchestratorInterface::class, $discovery);

        registrarRun($container, $dir);
    });

    it('falls back to the src directory for discovery', function (): void {
        $dir = registrarThemeDir(['src']);
        $container = new Container;
        $discovery = Mockery::mock(ModuleDiscoveryOrchestrator::class);
        $discovery->shouldReceive('discover')->once()->with($dir.'/src');
        $container->instance(ModuleDiscoveryOrchestratorInterface::class, $discovery);

        registrarRun($container, $dir);
    });

    it('skips discovery when the theme has neither app nor src', function (): void {
        $container = new Container;
        $discovery = Mockery::mock(ModuleDiscoveryOrchestrator::class);
        $discovery->shouldNotReceive('discover');

        $container->instance(ModuleDiscoveryOrchestratorInterface::class, $discovery);

        registrarRun($container, registrarThemeDir());
    });

    it('logs a discovery failure with the theme name', function (): void {
        $dir = registrarThemeDir(['app']);
        $container = new Container;
        $discovery = Mockery::mock(ModuleDiscoveryOrchestrator::class);
        $discovery->shouldReceive('discover')->andThrow(new RuntimeException('bad scan'));
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with('Theme discovery error for demo: bad scan');
        $container->instance(ModuleDiscoveryOrchestratorInterface::class, $discovery);
        $container->instance(LoggerInterface::class, $logger);

        registrarRun($container, $dir);
    });

    it('asks the configuration loader to load the theme config', function (): void {
        $dir = registrarThemeDir();
        $container = new Container;
        $loader = Mockery::mock(ModuleConfigurationLoader::class);
        $loader->shouldReceive('loadModuleConfiguration')->once()->with($dir, 'theme');
        $container->instance(ModuleConfigurationLoader::class, $loader);

        registrarRun($container, $dir);
    });

    it('logs a configuration loading failure', function (): void {
        $container = new Container;
        $loader = Mockery::mock(ModuleConfigurationLoader::class);
        $loader->shouldReceive('loadModuleConfiguration')->andThrow(new RuntimeException('cfg'));
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with('Failed to load theme configuration: cfg');
        $container->instance(ModuleConfigurationLoader::class, $loader);
        $container->instance(LoggerInterface::class, $logger);

        registrarRun($container, registrarThemeDir());
    });

    it('registers and initializes the theme components under a theme-scoped id', function (): void {
        $container = new Container;
        $manager = Mockery::mock(ModuleComponentManager::class);
        $manager->shouldReceive('registerModuleComponents')->once()->with('theme.demo', [
            ThemeInitializer::class,
            PatternComponent::class,
            Menus::class,
            Support::class,
            Sidebar::class,
            Templates::class,
            ImageSize::class,
        ]);
        $manager->shouldReceive('initializeModuleComponents')->once()->with('theme.demo');
        $container->instance(ModuleComponentManager::class, $manager);

        registrarRun($container, registrarThemeDir());
    });

    it('logs a component setup failure', function (): void {
        $container = new Container;
        $manager = Mockery::mock(ModuleComponentManager::class);
        $manager->shouldReceive('registerModuleComponents')->andThrow(new RuntimeException('comp'));
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with('Failed to setup theme components: comp');
        $container->instance(ModuleComponentManager::class, $manager);
        $container->instance(LoggerInterface::class, $logger);

        registrarRun($container, registrarThemeDir());
    });

    it('hands the theme to the route loader', function (): void {
        $container = new Container;
        $loader = Mockery::mock(ModuleRouteLoader::class);
        $loader->shouldReceive('loadModuleRoutes')->once()->with(Mockery::on(fn ($theme): bool => $theme->getName() === 'demo'));
        $container->instance(ModuleRouteLoader::class, $loader);

        registrarRun($container, registrarThemeDir());
    });

    it('logs a route loading failure', function (): void {
        $container = new Container;
        $loader = Mockery::mock(ModuleRouteLoader::class);
        $loader->shouldReceive('loadModuleRoutes')->andThrow(new RuntimeException('routes'));
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with('Failed to load theme routes: routes');
        $container->instance(ModuleRouteLoader::class, $loader);
        $container->instance(LoggerInterface::class, $logger);

        registrarRun($container, registrarThemeDir());
    });

    it('sets up assets, includes and Blade directives for the theme path', function (): void {
        $dir = registrarThemeDir();
        $container = new Container;
        $assets = Mockery::mock(ModuleAssetManager::class);
        $assets->shouldReceive('setupModuleAssets')->once()->with('demo', $dir, 'theme');
        $assets->shouldReceive('loadModuleIncludes')->once()->with($dir);
        $assets->shouldReceive('registerModuleBladeDirectives')->once()->with($dir);
        $container->instance(ModuleAssetManager::class, $assets);

        registrarRun($container, $dir);
    });

    it('logs an asset setup failure and skips the includes', function (): void {
        $container = new Container;
        $assets = Mockery::mock(ModuleAssetManager::class);
        $assets->shouldReceive('setupModuleAssets')->andThrow(new RuntimeException('assets'));
        $assets->shouldNotReceive('loadModuleIncludes');
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with('Failed to setup theme assets: assets');
        $container->instance(ModuleAssetManager::class, $assets);
        $container->instance(LoggerInterface::class, $logger);

        registrarRun($container, registrarThemeDir());
    });

    it('does not fail when logging itself fails', function (): void {
        $container = new Container;
        $loader = Mockery::mock(ModuleRouteLoader::class);
        $loader->shouldReceive('loadModuleRoutes')->andThrow(new RuntimeException('routes'));
        $container->instance(ModuleRouteLoader::class, $loader);

        $theme = registrarRun($container, registrarThemeDir());

        expect($theme->getName())->toBe('demo')->and($theme->isEnabled())->toBeTrue();
    });
});

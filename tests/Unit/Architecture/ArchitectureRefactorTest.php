<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Env;
use Illuminate\View\ViewFinderInterface;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Plugin\Application\Services\PluginManager;
use Pollora\Plugin\Infrastructure\Models\LaravelPluginModule;
use Pollora\Route\Domain\Contracts\WordPressTypeResolverInterface;
use Pollora\Route\Infrastructure\Services\Resolvers\WordPressTypeResolver;
use Pollora\Schedule\Application\Services\ScheduleStatus;
use Pollora\Schedule\Infrastructure\Drivers\LaravelQueueDriver;
use Pollora\Theme\Application\Services\ThemeManager;
use Pollora\Theme\Infrastructure\Models\LaravelThemeModule;

/**
 * The architecture refactors keep their behaviour: each test pins what a change moved
 * or replaced, so that the move cannot silently alter what a caller relied on.
 */
describe('WordPressTypeResolverInterface', function (): void {
    it('keeps the former name resolving to the interface that moved to the Route domain', function (): void {
        $former = 'Pollora\\Route\\Infrastructure\\Services\\Contracts\\WordPressTypeResolverInterface';

        expect(interface_exists($former))->toBeTrue()
            ->and((new ReflectionClass($former))->getName())->toBe(WordPressTypeResolverInterface::class);
    });

    it('is still implemented by the resolver under the former name', function (): void {
        $former = 'Pollora\\Route\\Infrastructure\\Services\\Contracts\\WordPressTypeResolverInterface';

        // The former name is an alias made when its file is autoloaded: load it here instead of
        // relying on the test above having done it, so this test passes alone and in any order.
        expect(interface_exists($former))->toBeTrue()
            ->and(is_subclass_of(WordPressTypeResolver::class, $former))->toBeTrue();
    });
});

describe('ScheduleStatus', function (): void {
    it('counts late events under the name the queue driver files them under', function (): void {
        $driverName = (new ReflectionClassConstant(ScheduleStatus::class, 'QUEUE_DRIVER'))->getValue();

        expect($driverName)->toBe(LaravelQueueDriver::NAME);
    });
});

describe('managers take the console detection service from the container they receive', function (): void {
    it('for plugins', function (): void {
        $container = new Container;
        $service = Mockery::mock(ConsoleDetectionService::class);
        $container->instance(ConsoleDetectionService::class, $service);

        $manager = new PluginManager($container, null);

        expect((new ReflectionProperty($manager, 'consoleDetectionService'))->getValue($manager))->toBe($service);
    });

    it('for themes', function (): void {
        $container = new Container;
        $service = Mockery::mock(ConsoleDetectionService::class);
        $container->instance(ConsoleDetectionService::class, $service);

        $manager = new ThemeManager($container, Mockery::mock(ViewFinderInterface::class), null);

        expect((new ReflectionProperty($manager, 'consoleDetectionService'))->getValue($manager))->toBe($service);
    });
});

describe('Vapor maintenance mode', function (): void {
    it('reads VAPOR_MAINTENANCE_MODE from the environment', function (): void {
        putenv('VAPOR_MAINTENANCE_MODE=on');

        try {
            expect(Env::get('VAPOR_MAINTENANCE_MODE'))->toBe(getenv('VAPOR_MAINTENANCE_MODE'))
                ->and(Env::get('VAPOR_MAINTENANCE_MODE'))->toBe('on');
        } finally {
            putenv('VAPOR_MAINTENANCE_MODE');
        }
    });

    /**
     * A container that answers the two cache paths the way Laravel's application does.
     */
    function vaporApplication(): Container
    {
        $app = new class extends Container
        {
            public function getCachedConfigPath(): string
            {
                return '/cache/config/config.php';
            }

            public function getCachedServicesPath(): string
            {
                return '/cache/services/services.php';
            }
        };
        $app->instance('config', new Repository);
        Container::setInstance($app);

        return $app;
    }

    it('caches the plugin and theme services next to the configuration on Vapor', function (): void {
        putenv('VAPOR_MAINTENANCE_MODE=on');
        $app = vaporApplication();

        try {
            expect((new LaravelPluginModule('blog', '/tmp/plugin', $app))->getCachedServicesPath())->toBe('/cache/config/blog_plugin.php')
                ->and((new LaravelThemeModule('journal', '/tmp/theme', $app))->getCachedServicesPath())->toBe('/cache/config/journal_theme.php');
        } finally {
            putenv('VAPOR_MAINTENANCE_MODE');
        }
    });

    it('caches them next to the services outside Vapor', function (): void {
        putenv('VAPOR_MAINTENANCE_MODE');
        $app = vaporApplication();

        expect((new LaravelPluginModule('blog', '/tmp/plugin', $app))->getCachedServicesPath())->toBe('/cache/services/blog_plugin.php')
            ->and((new LaravelThemeModule('journal', '/tmp/theme', $app))->getCachedServicesPath())->toBe('/cache/services/journal_theme.php');
    });
});

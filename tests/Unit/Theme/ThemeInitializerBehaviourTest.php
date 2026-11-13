<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Mockery as m;
use Mockery\MockInterface;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Hook\Domain\Contract\HookInterface;
use Pollora\Theme\Domain\Contracts\ContainerInterface;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Contracts\WordPressThemeInterface;
use Pollora\Theme\Domain\Exceptions\ThemeSetupException;
use Pollora\Theme\Domain\Models\ThemeInitializer;
use Pollora\Theme\Domain\Support\ThemeConfig;

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
    Container::setInstance(new class extends Container
    {
        public function basePath($path = ''): string
        {
            return '/srv/site'.($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
        }
    });
});

afterEach(function (): void {
    Container::setInstance($this->previousContainer);
    ThemeConfig::reset();
});

/**
 * @param  array<class-string|string, mixed>  $services  extra services the container port answers with
 * @return array{0: ThemeInitializer, 1: MockInterface, 2: ArrayObject<string, Closure>}
 */
function behaviourInitializer(array $services = [], ?ThemeModuleInterface $active = null): array
{
    $actions = new ArrayObject;
    $hook = m::mock(HookInterface::class);
    $action = m::mock(Action::class);
    $action->shouldReceive('add')->andReturnUsing(function (string $name, Closure $callback) use ($actions, $hook): HookInterface {
        $actions[$name] = $callback;

        return $hook;
    });
    $filter = m::mock(Filter::class);
    $filter->shouldReceive('add')->andReturn($hook);
    $registrar = m::mock(ThemeRegistrarInterface::class);
    $registrar->shouldReceive('getActiveTheme')->andReturn($active);

    $app = m::mock(ContainerInterface::class);
    $app->shouldReceive('get')->with(Action::class)->andReturn($action);
    $app->shouldReceive('get')->with(Filter::class)->andReturn($filter);
    $app->shouldReceive('get')->with(WordPressThemeInterface::class)->andReturn(m::mock(WordPressThemeInterface::class)->shouldIgnoreMissing());
    $app->shouldReceive('get')->with(ThemeRegistrarInterface::class)->andReturn($registrar);
    foreach ($services as $id => $service) {
        $app->shouldReceive('get')->with($id)->andReturn($service);
    }

    $initializer = new ThemeInitializer($app, m::mock(ConfigRepositoryInterface::class));

    return [$initializer, $app, $actions];
}

describe('ThemeInitializer theme loading', function (): void {
    it('refuses to set up a theme without a name', function (?string $name): void {
        [$initializer] = behaviourInitializer([ThemeService::class => m::mock(ThemeService::class)]);

        $initializer->setThemes($name);
    })->with([null, '', '0'])->throws(ThemeSetupException::class, 'Theme name is required');

    it('loads the named theme through the theme service', function (): void {
        $service = m::mock(ThemeService::class);
        $loaded = [];
        $service->shouldReceive('load')->andReturnUsing(function (string $name) use (&$loaded): void {
            $loaded[] = $name;
        });
        [$initializer] = behaviourInitializer([ThemeService::class => $service]);

        $initializer->setThemes('demo');

        expect($loaded)->toBe(['demo']);
    });

    it('falls back to the "theme" binding when the ThemeService interface is not resolvable', function (): void {
        $service = m::mock(ThemeService::class);
        $loaded = [];
        $service->shouldReceive('load')->andReturnUsing(function (string $name) use (&$loaded): void {
            $loaded[] = $name;
        });
        [$initializer] = behaviourInitializer([ThemeService::class => null, 'theme' => $service]);

        $initializer->setThemes('demo');

        expect($loaded)->toBe(['demo']);
    });

    it('fails clearly when no theme service can be found at all', function (): void {
        [$initializer] = behaviourInitializer([ThemeService::class => null, 'theme' => null]);

        $initializer->setThemes('demo');
    })->throws(ThemeSetupException::class, 'Unable to resolve ThemeService');

    it('tells whether the active template is the given theme', function (): void {
        $wpTheme = m::mock(WordPressThemeInterface::class);
        $wpTheme->shouldReceive('getTemplate')->andReturn('parent');
        [$initializer] = behaviourInitializer();
        (new ReflectionProperty($initializer, 'wpTheme'))->setValue($initializer, $wpTheme);

        expect($initializer->isThemeIdentical('parent'))->toBeTrue()
            ->and($initializer->isThemeIdentical('child'))->toBeFalse();
    });
});

describe('ThemeInitializer providers and config', function (): void {
    it('registers the service providers listed in the theme config once the theme is initialized', function (): void {
        $config = m::mock(ConfigRepositoryInterface::class);
        $config->shouldReceive('get')->with('theme.providers', [])->andReturn(['App\\ThemeProviderA', 'App\\ThemeProviderB']);
        $config->shouldReceive('get')->andReturnUsing(fn (string $key, $default = null) => $default);
        ThemeConfig::setRepository($config);
        $active = m::mock(ThemeModuleInterface::class);
        $active->shouldReceive('getPath')->andReturn('/srv/site/themes/demo');
        $active->shouldReceive('getName')->andReturn('demo');
        $service = m::mock(ThemeService::class);
        $service->shouldReceive('load')->with('demo');
        [$initializer, $app, $actions] = behaviourInitializer([ThemeService::class => $service], $active);
        $registered = [];
        $app->shouldReceive('registerProvider')->andReturnUsing(function (string $provider) use (&$registered): void {
            $registered[] = $provider;
        });
        $app->shouldReceive('bindShared')->once();

        $initializer->register();
        $actions['after_setup_theme']();

        expect($registered)->toBe(['App\\ThemeProviderA', 'App\\ThemeProviderB']);
    });

    it('does nothing at after_setup_theme when no theme registered itself', function (): void {
        [$initializer, $app, $actions] = behaviourInitializer();
        $app->shouldNotReceive('registerProvider');
        $app->shouldNotReceive('bindShared');

        $initializer->register();

        expect($actions['after_setup_theme']())->toBeNull();
    });

    it('merges a config file under the values already configured', function (): void {
        $file = sys_get_temp_dir().'/pollora-init-'.bin2hex(random_bytes(6)).'.php';
        file_put_contents($file, "<?php return ['a' => 'file', 'b' => 'file'];");
        [$initializer, $app] = behaviourInitializer();
        $app->shouldReceive('getConfig')->with('theme.x', [])->andReturn(['b' => 'app']);
        $stored = [];
        $app->shouldReceive('setConfig')->andReturnUsing(function (string $key, array $value) use (&$stored): void {
            $stored = [$key, $value];
        });

        try {
            (fn () => $this->mergeConfigFrom($file, 'theme.x'))->call($initializer);
        } finally {
            unlink($file);
        }

        expect($stored)->toBe(['theme.x', ['a' => 'file', 'b' => 'app']]);
    });

    it('leaves the config alone when the file does not exist', function (): void {
        [$initializer, $app] = behaviourInitializer();
        $app->shouldReceive('getConfig')->andReturn([]);
        $app->shouldNotReceive('setConfig');

        $result = (fn () => $this->mergeConfigFrom('/no/such/file.php', 'theme.x'))->call($initializer);

        expect($result)->toBeNull();
    });

    it('answers the theme root option with the configured root', function (): void {
        [$initializer] = behaviourInitializer();

        expect((fn () => $this->resetThemeRootOption('/stale/path'))->call($initializer))->toBe('/srv/site'.DIRECTORY_SEPARATOR.'themes')
            ->and((fn () => $this->resetThemeRootOption(false))->call($initializer))->toBe('/srv/site'.DIRECTORY_SEPARATOR.'themes');
    });
});

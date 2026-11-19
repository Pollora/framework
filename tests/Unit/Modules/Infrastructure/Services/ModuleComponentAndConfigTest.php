<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\HookInterface;
use Pollora\Modules\Domain\Exceptions\ModuleComponentException;
use Pollora\Modules\Infrastructure\Services\ModuleComponentManager;
use Pollora\Modules\Infrastructure\Services\ModuleConfigurationLoader;
use Psr\Log\LoggerInterface;

class ComponentFixtureOk
{
    public static int $registered = 0;

    public function register(): void
    {
        self::$registered++;
    }
}

class ComponentFixturePlain {}

class ComponentFixtureBroken
{
    public function register(): void
    {
        throw new RuntimeException('component exploded');
    }
}

afterEach(function (): void {
    Mockery::close();
    Container::setInstance();
});

describe('ModuleComponentManager', function (): void {
    beforeEach(function (): void {
        ComponentFixtureOk::$registered = 0;
        $this->app = new Container;
        $this->config = new Repository(['app' => ['debug' => false]]);
        $this->app->instance('config', $this->config);
        Container::setInstance($this->app);
        $this->manager = new ModuleComponentManager($this->app);
    });

    it('binds each component as a singleton under a key naming the module and the class', function (): void {
        $this->manager->registerModuleComponents('theme-a', [ComponentFixtureOk::class]);

        expect($this->app->bound('module.theme-a.component.ComponentFixtureOk'))->toBeTrue()
            ->and($this->manager->getModuleComponents('theme-a'))->toBe([ComponentFixtureOk::class])
            ->and($this->app->make('module.theme-a.component.ComponentFixtureOk'))
            ->toBe($this->app->make('module.theme-a.component.ComponentFixtureOk'));
    });

    it('does not rebind a component that is already bound', function (): void {
        $existing = new ComponentFixturePlain;
        $this->app->instance('module.theme-a.component.ComponentFixtureOk', $existing);

        $this->manager->registerModuleComponents('theme-a', [ComponentFixtureOk::class]);

        expect($this->app->make('module.theme-a.component.ComponentFixtureOk'))->toBe($existing);
    });

    it('calls register() on initialisation only for components that have one', function (): void {
        $this->manager->registerModuleComponents('m', [ComponentFixtureOk::class, ComponentFixturePlain::class]);

        $this->manager->initializeModuleComponents('m');

        expect(ComponentFixtureOk::$registered)->toBe(1);
    });

    it('ignores the initialisation of a module with no components', function (): void {
        $this->manager->initializeModuleComponents('unknown');

        expect($this->manager->getModuleComponents('unknown'))->toBe([])
            ->and(ComponentFixtureOk::$registered)->toBe(0);
    });

    it('logs a failing component and carries on when debug is off', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(Mockery::on(fn (string $m): bool => str_contains($m, 'ComponentFixtureBroken')), Mockery::type('array'));
        $this->app->instance(LoggerInterface::class, $logger);
        $manager = new ModuleComponentManager($this->app);
        $manager->registerModuleComponents('m', [ComponentFixtureBroken::class, ComponentFixtureOk::class]);

        $manager->initializeModuleComponents('m');

        expect(ComponentFixtureOk::$registered)->toBe(1);
    });

    it('raises a ModuleComponentException with the cause when debug is on', function (): void {
        $this->config->set('app.debug', true);
        $this->manager->registerModuleComponents('m', [ComponentFixtureBroken::class]);

        try {
            $this->manager->initializeModuleComponents('m');
            $this->fail('An exception was expected.');
        } catch (ModuleComponentException $moduleComponentException) {
            expect($moduleComponentException->getMessage())->toContain('ComponentFixtureBroken', 'module m', 'component exploded')
                ->and($moduleComponentException->getPrevious())->toBeInstanceOf(RuntimeException::class);
        }
    });

    it('forgets the components of a module that is unregistered', function (): void {
        $this->manager->registerModuleComponents('m', [ComponentFixtureOk::class]);

        $this->manager->unregisterModuleComponents('m');
        $this->manager->unregisterModuleComponents('never-registered');
        $this->manager->initializeModuleComponents('m');

        expect($this->manager->getModuleComponents('m'))->toBe([])
            ->and(ComponentFixtureOk::$registered)->toBe(0);
    });
});

describe('ModuleConfigurationLoader', function (): void {
    beforeEach(function (): void {
        $this->dir = sys_get_temp_dir().'/pollora-modconfig-'.uniqid();
        mkdir($this->dir.'/config', 0777, true);
        $this->app = new Container;
        $this->action = Mockery::mock(Action::class);
        $this->app->instance(Action::class, $this->action);
        $this->repo = Mockery::mock(ConfigRepositoryInterface::class);
    });

    afterEach(function (): void {
        foreach (glob($this->dir.'/config/*') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->dir.'/config');
        @rmdir($this->dir);
    });

    it('defers loading to after_setup_theme at priority 1', function (): void {
        $captured = null;
        $this->action->shouldReceive('add')->once()->with('after_setup_theme', Mockery::on(function ($cb) use (&$captured): bool {
            $captured = $cb;

            return true;
        }), 1)->andReturn(Mockery::mock(HookInterface::class));
        $this->repo->shouldReceive('set')->never();

        (new ModuleConfigurationLoader($this->app, $this->repo))->loadModuleConfiguration($this->dir, 'theme');

        expect($captured)->toBeCallable();
    });

    it('loads each array config file under type.filename and skips non arrays', function (): void {
        file_put_contents($this->dir.'/config/seo.php', '<?php return ["title" => "x"];');
        file_put_contents($this->dir.'/config/flag.php', '<?php return true;');
        file_put_contents($this->dir.'/config/notes.txt', 'ignored');
        $this->action->shouldReceive('add')->once()->andReturnUsing(function ($hook, $cb) {
            $cb();

            return Mockery::mock(HookInterface::class);
        });
        $this->repo->shouldReceive('set')->once()->with('plugin.seo', ['title' => 'x']);

        (new ModuleConfigurationLoader($this->app, $this->repo))->loadModuleConfiguration($this->dir, 'plugin');
    });

    it('does nothing when the module has no config directory', function (): void {
        $this->action->shouldReceive('add')->once()->andReturnUsing(function ($hook, $cb) {
            $cb();

            return Mockery::mock(HookInterface::class);
        });
        $this->repo->shouldReceive('set')->never();

        (new ModuleConfigurationLoader($this->app, $this->repo))->loadModuleConfiguration($this->dir.'/missing', 'theme');
    });

    it('logs a config file that throws and keeps loading the others', function (): void {
        file_put_contents($this->dir.'/config/a_bad.php', '<?php throw new RuntimeException("bad");');
        file_put_contents($this->dir.'/config/b_good.php', '<?php return ["ok" => 1];');
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(Mockery::on(fn (string $m): bool => str_contains($m, 'a_bad.php')), Mockery::type('array'));
        $this->app->instance(LoggerInterface::class, $logger);
        $this->action->shouldReceive('add')->once()->andReturnUsing(function ($hook, $cb) {
            $cb();

            return Mockery::mock(HookInterface::class);
        });
        $this->repo->shouldReceive('set')->once()->with('theme.b_good', ['ok' => 1]);

        (new ModuleConfigurationLoader($this->app, $this->repo))->loadModuleConfiguration($this->dir, 'theme');
    });

    it('reads, writes and tests values under the module type prefix', function (): void {
        $this->action->shouldReceive('add')->andReturn(Mockery::mock(HookInterface::class));
        $this->repo->shouldReceive('get')->once()->with('theme.colors.primary', 'red')->andReturn('blue');
        $this->repo->shouldReceive('set')->once()->with('theme.colors.primary', 'green');
        $this->repo->shouldReceive('has')->once()->with('plugin.x')->andReturn(true);
        $loader = new ModuleConfigurationLoader($this->app, $this->repo);

        expect($loader->getModuleConfig('theme', 'colors.primary', 'red'))->toBe('blue')
            ->and($loader->hasModuleConfig('plugin', 'x'))->toBeTrue();
        $loader->setModuleConfig('theme', 'colors.primary', 'green');
    });
});

<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Role\Application\Services\RoleDefinitionBuilder;
use Pollora\Role\Infrastructure\Adapters\WordPressRoleInjector;
use Pollora\Role\Infrastructure\Providers\RoleServiceProvider;
use Pollora\Role\Infrastructure\Services\RoleDiscovery;
use Pollora\Role\UI\Console\RoleMakeCommand;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->app = new Application(sys_get_temp_dir());
    $this->app->instance('config', new Repository(['roles' => ['super_roles' => ['administrator', 'network_admin']]]));
    $this->app->instance(LoggerInterface::class, Mockery::mock(LoggerInterface::class));

    $this->action = Mockery::mock(Action::class);
    $this->app->instance(Action::class, $this->action);
    $this->filter = Mockery::mock(Filter::class);
    $this->app->instance(Filter::class, $this->filter);
    $this->provider = new RoleServiceProvider($this->app);
    $this->provider->register();
});

it('binds the discovery and the injector, with the configured super roles', function (): void {
    expect($this->app->make(RoleDiscovery::class))->toBeInstanceOf(RoleDiscovery::class)
        ->and($this->app->make(WordPressRoleInjector::class))->toBe($this->app->make(WordPressRoleInjector::class))
        ->and((new ReflectionProperty(RoleDefinitionBuilder::class, 'superRoles'))->getValue($this->app->make(RoleDefinitionBuilder::class)))->toBe(['administrator', 'network_admin']);
});

it('subscribes the injector to wp_roles_init, early, reports warnings once on init, and translates labels', function (): void {
    $this->action->shouldReceive('add')->once()->with('wp_roles_init', Mockery::type(Closure::class), 1)->andReturnSelf();
    $this->action->shouldReceive('add')->once()->with('init', Mockery::type(Closure::class), PHP_INT_MAX)->andReturnSelf();
    $this->filter->shouldReceive('add')->once()->with('gettext_with_context_default', Mockery::type(Closure::class), 10, 3)->andReturnSelf();

    $this->provider->boot();
});

it('generates a role class with its slug and label', function (): void {
    $command = new RoleMakeCommand(new Filesystem);
    $stub = file_get_contents((new ReflectionMethod($command, 'getStub'))->invoke($command));

    $class = (new ReflectionMethod($command, 'replaceClass'))->invoke($command, $stub, 'App\\Cms\\Roles\\EventManager');

    expect($class)->toContain("#[Role('event_manager', label: 'Event Manager', inherits: 'subscriber')]")
        ->toContain('final class EventManager')
        ->and($command->getName())->toBe('pollora:make:role');
});

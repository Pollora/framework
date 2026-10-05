<?php

declare(strict_types=1);

namespace Pollora\Role\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Role\Application\Services\CapabilityOwnerReader;
use Pollora\Role\Application\Services\RoleDefinitionBuilder;
use Pollora\Role\Application\Services\RoleRegistry;
use Pollora\Role\Domain\Services\PostTypeCapabilityMap;
use Pollora\Role\Domain\Services\RoleCompiler;
use Pollora\Role\Infrastructure\Adapters\WordPressRoleInjector;
use Pollora\Role\Infrastructure\Services\RoleDiscovery;
use Pollora\Role\UI\Console\RoleMakeCommand;
use Psr\Log\LoggerInterface;

/**
 * Roles declared in code: `#[Role]`, `#[ModifyRole]`, `#[CapabilitySet]`,
 * injected into WordPress on `wp_roles_init`.
 *
 * Must boot before WordPress loads (before the WordPress service provider), so
 * that the injector is subscribed before the first `WP_Roles` exists.
 */
class RoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/roles.php', 'roles');

        $this->app->singleton(CapabilityOwnerReader::class);
        $this->app->singleton(PostTypeCapabilityMap::class);
        $this->app->singleton(RoleCompiler::class);
        $this->app->singleton(RoleRegistry::class);

        $this->app->singleton(RoleDefinitionBuilder::class, fn (Application $app): RoleDefinitionBuilder => new RoleDefinitionBuilder(
            $app->make(CapabilityOwnerReader::class),
            $this->superRoles(),
        ));

        $this->app->singleton(WordPressRoleInjector::class, fn (Application $app): WordPressRoleInjector => new WordPressRoleInjector(
            $app->make(RoleRegistry::class),
            $app->make(RoleCompiler::class),
            $this->superRoles(),
            $app->make(LoggerInterface::class),
        ));

        $this->app->singleton(RoleDiscovery::class, fn (Application $app): RoleDiscovery => new RoleDiscovery(
            $app->make(RoleDefinitionBuilder::class),
            $app->make(RoleRegistry::class),
            $app->make(WordPressRoleInjector::class),
            $app->make(LoggerInterface::class),
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([RoleMakeCommand::class]);
        }
    }

    public function boot(): void
    {
        $action = $this->app->make(Action::class);
        $injector = $this->app->make(WordPressRoleInjector::class);

        // Early priority: listeners after it see the roles as the code declares them.
        $action->add('wp_roles_init', $injector->inject(...), 1);
        $action->add('init', $injector->reportWarnings(...), PHP_INT_MAX);
    }

    /**
     * @return list<string>
     */
    private function superRoles(): array
    {
        return array_values((array) $this->app->make('config')->get('roles.super_roles', ['administrator']));
    }
}

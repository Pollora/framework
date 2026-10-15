<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\View\Compilers\BladeCompiler;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Role\Application\Services\RoleDefinitionBuilder;
use Pollora\Role\Application\Services\RoleRegistry;
use Pollora\Role\Domain\Services\RoleCompiler;
use Pollora\Role\Infrastructure\Middleware\EnsureUserHasRole;
use Pollora\Role\Infrastructure\Providers\RoleServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Unit\Role\Fixtures\EditorAdjustments;
use Tests\Unit\Role\Fixtures\Event;
use Tests\Unit\Role\Fixtures\EventManager;
use Tests\Unit\Role\Fixtures\Genre;
use Tests\Unit\Role\Fixtures\Venue;

/**
 * Roles declared in code, from the attributes to the capabilities WordPress stores, with the real provider, the
 * real Laravel container and the real compiler; only WordPress's own role storage is out of the picture (the
 * stored roles are given). It freezes what the extraction of `pollora/roles` must keep, and the Laravel bridge
 * (the `role` middleware alias, `@role`) that stays in the framework.
 */
beforeEach(function (): void {
    $this->app = new Application(sys_get_temp_dir());
    $this->app->instance('config', new Repository(['roles' => ['super_roles' => ['administrator']]]));
    $this->app->instance(LoggerInterface::class, new NullLogger);

    $this->action = Mockery::mock(Action::class)->shouldIgnoreMissing();
    $this->app->instance(Action::class, $this->action);
    $this->app->instance(Filter::class, Mockery::mock(Filter::class)->shouldIgnoreMissing());
    $this->app->instance('events', new Dispatcher);
    (new RoleServiceProvider($this->app))->register();

    $this->stored = [
        'administrator' => ['name' => 'Administrator', 'capabilities' => ['manage_options' => true, 'edit_posts' => true, 'edit_theme_options' => true]],
        'editor' => ['name' => 'Editor', 'capabilities' => ['edit_posts' => true, 'edit_theme_options' => true, 'moderate_comments' => true]],
        'author' => ['name' => 'Author', 'capabilities' => ['read' => true, 'edit_posts' => true, 'publish_posts' => true, 'upload_files' => true]],
        'subscriber' => ['name' => 'Subscriber', 'capabilities' => ['read' => true]],
    ];
});

it('compiles the declared roles and modifications into the capabilities WordPress stores', function (): void {
    $builder = $this->app->make(RoleDefinitionBuilder::class);
    $registry = $this->app->make(RoleRegistry::class);
    $registry->addPostType(Event::class);
    $registry->addPostType(Venue::class);
    $registry->addTaxonomy(Genre::class);
    $registry->addRole($builder->build(EventManager::class));
    $registry->addModification($builder->build(EditorAdjustments::class));

    $roles = $this->app->make(RoleCompiler::class)->compile(
        $this->stored,
        $registry->definitions(),
        $registry->modifications(),
        ['administrator'],
        $registry->declaredCapabilities(),
    );

    ksort($roles['event_manager']['capabilities']);
    ksort($roles['editor']['capabilities']);
    ksort($roles['administrator']['capabilities']);

    expect(array_keys($roles))->toBe(['administrator', 'editor', 'author', 'subscriber', 'event_manager'])
        ->and($roles['event_manager']['name'])->toBe('Event manager')
        ->and($roles['event_manager']['_pollora'])->toBe(['managed' => true])
        ->and($roles['event_manager']['capabilities'])->not->toHaveKey('publish_posts')
        ->and($roles['event_manager']['capabilities'])->toHaveKeys(['read', 'export_attendees', 'scan_tickets', 'edit_others_events', 'open_venues', 'manage_genres'])
        ->and($roles['editor']['capabilities'])->not->toHaveKey('edit_theme_options')
        ->and($roles['editor']['capabilities'])->toHaveKey('scan_tickets')
        ->and($roles['administrator']['capabilities'])->toHaveKeys(['edit_others_events', 'manage_genres'])
        ->and($roles['administrator']['capabilities'])->not->toHaveKey('export_attendees');
    expect(array_keys($roles['event_manager']['capabilities']))->toBe(['assign_genres', 'delete_events', 'delete_others_events', 'delete_private_events', 'delete_published_events', 'delete_published_venues', 'delete_venues', 'edit_events', 'edit_genres', 'edit_others_events', 'edit_posts', 'edit_private_events', 'edit_published_events', 'edit_published_venues', 'edit_venues', 'export_attendees', 'manage_genres', 'open_venues', 'publish_events', 'read', 'read_private_events', 'scan_tickets', 'upload_files']);
});

it('aliases the role middleware and compiles @role once the application has booted', function (): void {
    $this->app->instance('router', $router = new Router(new Dispatcher));
    $this->app->instance('blade.compiler', $blade = new BladeCompiler(new Filesystem, sys_get_temp_dir()));

    $provider = new RoleServiceProvider($this->app);
    $provider->boot();

    $this->app->make('router');
    $this->app->booted(static fn (): null => null);
    $this->app->boot();
    $this->app->make('blade.compiler');

    expect($router->getMiddleware()['role'] ?? null)->toBe(EnsureUserHasRole::class)
        ->and($blade->compileString("@role('editor') shown @endrole"))->toContain('endif');
});

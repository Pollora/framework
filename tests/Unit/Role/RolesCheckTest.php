<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Doctor\Domain\Enums\CheckStatus;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Role\Application\Services\CapabilityOwnerReader;
use Pollora\Role\Application\Services\RoleDefinitionBuilder;
use Pollora\Role\Application\Services\RoleRegistry;
use Pollora\Role\Domain\Services\PostTypeCapabilityMap;
use Pollora\Role\Domain\Services\RoleCompiler;
use Pollora\Role\Infrastructure\Adapters\WordPressRoleInjector;
use Pollora\Role\Infrastructure\Checks\RolesCheck;
use Tests\Unit\Role\Fixtures\EventManager;
use Tests\Unit\Role\Fixtures\FakeRoleUsage;

require_once __DIR__.'/Fixtures/wordpress-roles.php';

beforeEach(function (): void {
    $this->registry = new RoleRegistry(new CapabilityOwnerReader, new PostTypeCapabilityMap);
    $this->injector = new WordPressRoleInjector($this->registry, new RoleCompiler);
    $this->wpRoles = new WP_Roles;
    $this->wpRoles->roles = [
        'administrator' => ['name' => 'Administrator', 'capabilities' => ['manage_options' => true, 'moderate_comments' => true]],
        'subscriber' => ['name' => 'Subscriber', 'capabilities' => ['read' => true]],
    ];
    $this->defaultRole = 'subscriber';

    Functions\when('did_action')->justReturn(1);
    Functions\when('wp_roles')->alias(fn (): WP_Roles => $this->wpRoles);
    Functions\when('get_option')->alias(fn (string $name): mixed => $name === 'default_role' ? $this->defaultRole : false);

    $this->check = fn (array $users = []): RolesCheck => new RolesCheck(new FakeRoleUsage($users), $this->injector);
});

it('passes when every role users carry exists', function (): void {
    $result = ($this->check)([1 => ['login' => 'admin', 'capabilities' => ['administrator' => true]]])->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Ok)
        ->and($result->summary)->toBe('2 role(s); every role users carry exists.');
});

it('names the users of a role removed from the code, and the capabilities given one by one', function (): void {
    $result = ($this->check)([
        3 => ['login' => 'jane', 'capabilities' => ['event_manager' => true]],
        4 => ['login' => 'joe', 'capabilities' => ['subscriber' => true, 'event_manager' => true, 'moderate_comments' => true, 'old_cap' => false]],
    ])->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Warning)
        ->and($result->details)->toBe([
            '2 user(s) carry "event_manager", which is no role WordPress knows and no capability a role grants — a role removed from the code, most likely: jane, joe',
            'Capabilities given to users one by one, outside the roles the code declares: moderate_comments (1 user(s))',
        ]);
});

it('fails when new users would get a role that does not exist', function (): void {
    $this->defaultRole = 'event_manager';

    $result = ($this->check)()->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Error)
        ->and($result->details)->toBe(['default_role is "event_manager", a role WordPress does not know: new users get no capability']);
});

it('reports what the role declarations could not apply', function (): void {
    $this->registry->addRole((new RoleDefinitionBuilder(new CapabilityOwnerReader))->build(EventManager::class));
    $this->injector->inject($this->wpRoles);

    expect(($this->check)()->run(RunContext::Console)->details)
        ->toContain('Not applied: Tests\Unit\Role\Fixtures\EventManager: the post type "venue" was not found with its own capabilities; its grant is ignored.');
});

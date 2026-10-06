<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Pollora\Role\Application\Services\CapabilityOwnerReader;
use Pollora\Role\Application\Services\RoleDefinitionBuilder;
use Pollora\Role\Application\Services\RoleRegistry;
use Pollora\Role\Domain\Contracts\RoleUsageInterface;
use Pollora\Role\Domain\Services\PostTypeCapabilityMap;
use Pollora\Role\Domain\Services\RoleCompiler;
use Pollora\Role\Infrastructure\Adapters\WordPressRoleInjector;
use Pollora\Role\UI\Console\RoleListCommand;
use Pollora\Role\UI\Console\RoleShowCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Unit\Role\Fixtures\EditorAdjustments;
use Tests\Unit\Role\Fixtures\EventManager;
use Tests\Unit\Role\Fixtures\FakeRoleUsage;

require_once __DIR__.'/Fixtures/wordpress-roles.php';

/**
 * @param  array<string, mixed>  $input
 * @return array{0: int, 1: string}
 */
function runRoleCommand(Command $command, Application $app, array $input = []): array
{
    Container::setInstance($app);
    $command->setLaravel($app);
    $output = new BufferedOutput;
    $code = $command->run(new ArrayInput($input), $output);
    Container::setInstance(new Container);

    return [$code, $output->fetch()];
}

beforeEach(function (): void {
    $this->app = new Application(sys_get_temp_dir());
    $this->app->instance('config', new Repository(['roles' => ['super_roles' => ['administrator']]]));

    $registry = new RoleRegistry(new CapabilityOwnerReader, new PostTypeCapabilityMap);
    $builder = new RoleDefinitionBuilder(new CapabilityOwnerReader);
    $registry->addRole($builder->build(EventManager::class));
    $registry->addModification($builder->build(EditorAdjustments::class));

    $this->app->instance(RoleRegistry::class, $registry);
    $this->app->instance(RoleUsageInterface::class, new FakeRoleUsage([
        1 => ['login' => 'jane', 'capabilities' => ['event_manager' => true]],
    ]));

    // The roles as WordPress has them once the code is injected
    $this->wpRoles = new WP_Roles;
    $this->wpRoles->roles = [
        'administrator' => ['name' => 'Administrator', 'capabilities' => ['manage_options' => true]],
        'editor' => ['name' => 'Editor', 'capabilities' => ['edit_posts' => true, 'moderate_comments' => true]],
        'author' => ['name' => 'Author', 'capabilities' => ['read' => true, 'edit_posts' => true, 'publish_posts' => true, 'upload_files' => true]],
    ];
    (new WordPressRoleInjector($registry, new RoleCompiler))->inject($this->wpRoles);
    Functions\when('wp_roles')->alias(fn (): WP_Roles => $this->wpRoles);
});

it('lists each role with its origin, capabilities and users', function (): void {
    [$code, $output] = runRoleCommand(new RoleListCommand, $this->app, ['--json' => true]);
    $roles = array_column(json_decode($output, true), null, 'slug');

    expect($code)->toBe(0)
        ->and($roles['event_manager']['origin'])->toBe('declared by '.EventManager::class)
        ->and($roles['event_manager']['users'])->toBe(1)
        ->and($roles['editor']['origin'])->toBe('stored, modified by '.EditorAdjustments::class)
        ->and($roles['author']['origin'])->toBe('stored');
});

it('shows where each capability of a declared role comes from, and what the code removes', function (): void {
    [$code, $output] = runRoleCommand(new RoleShowCommand, $this->app, ['role' => EventManager::class, '--json' => true]);
    $role = json_decode($output, true);
    $from = array_column($role['capabilities'], 'from', 'capability');

    expect($code)->toBe(0)
        ->and($role['slug'])->toBe('event_manager')
        ->and($role['inherits'])->toBe('author')
        ->and($from['read'])->toBe('inherited from author')
        ->and($from['upload_files'])->toBe('granted by EventManager')
        ->and($from['export_attendees'])->toBe('granted by EventManager')
        ->and($role['removed'])->toBe(['publish_posts']);
});

it('refuses a role WordPress does not have', function (): void {
    [$code, $output] = runRoleCommand(new RoleShowCommand, $this->app, ['role' => 'ghost']);

    expect($code)->toBe(1)->and($output)->toContain('WordPress has no role "ghost"');
});

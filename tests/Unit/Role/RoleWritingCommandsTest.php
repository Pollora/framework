<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Pollora\Role\Application\Services\CapabilityOwnerReader;
use Pollora\Role\Application\Services\RoleDefinitionBuilder;
use Pollora\Role\Application\Services\RoleRegistry;
use Pollora\Role\Domain\Contracts\RoleStoreInterface;
use Pollora\Role\Domain\Contracts\RoleUsageInterface;
use Pollora\Role\Domain\Services\PostTypeCapabilityMap;
use Pollora\Role\Domain\Services\StoredEntryClassifier;
use Pollora\Role\UI\Console\RoleDumpCommand;
use Pollora\Role\UI\Console\RoleImportCommand;
use Pollora\Role\UI\Console\RolePruneCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Unit\Role\Fixtures\EventManager;
use Tests\Unit\Role\Fixtures\FakeRoleStore;
use Tests\Unit\Role\Fixtures\FakeRoleUsage;

require_once __DIR__.'/Fixtures/wordpress-roles.php';

/**
 * @param  array<string, mixed>  $input
 * @return array{0: int, 1: string}
 */
function runWritingRoleCommand(Command $command, Application $app, array $input = []): array
{
    Container::setInstance($app);
    $command->setLaravel($app);
    $output = new BufferedOutput;
    $code = $command->run(new ArrayInput($input), $output);
    Container::setInstance(new Container);

    return [$code, $output->fetch()];
}

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pollora-roles-'.uniqid();
    mkdir($this->root.'/app', 0777, true);
    file_put_contents($this->root.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
    $this->app = new Application($this->root);
    $this->app->instance('files', new Filesystem);
    $this->app->instance('config', new Repository(['app' => ['name' => 'Test']]));

    $this->registry = new RoleRegistry(new CapabilityOwnerReader, new PostTypeCapabilityMap);
    $this->app->instance(RoleRegistry::class, $this->registry);
    $this->app->instance(StoredEntryClassifier::class, new StoredEntryClassifier);

    $this->wpRoles = new WP_Roles;
    $this->wpRoles->roles = [
        'administrator' => ['name' => 'Administrator', 'capabilities' => ['manage_options' => true, 'moderate_comments' => true]],
        'author' => ['name' => 'Author', 'capabilities' => ['read' => true, 'edit_posts' => true, 'publish_posts' => true]],
        'subscriber' => ['name' => 'Subscriber', 'capabilities' => ['read' => true]],
    ];
    Functions\when('wp_roles')->alias(fn (): WP_Roles => $this->wpRoles);

    $this->store = new FakeRoleStore(
        stored: [
            ...$this->wpRoles->roles,
            'old_manager' => ['name' => 'Old manager', 'capabilities' => ['read' => true], '_pollora' => ['managed' => true]],
            'venue_staff' => ['name' => 'Venue staff', 'capabilities' => ['read' => true, 'edit_posts' => true, 'scan_tickets' => true, 'edit_users' => true, 'spam' => false]],
        ],
        userRoles: [3 => [], 4 => ['author']],
    );
    $this->app->instance(RoleStoreInterface::class, $this->store);
    $this->app->instance(RoleUsageInterface::class, new FakeRoleUsage([
        3 => ['login' => 'jane', 'capabilities' => ['event_manager' => true]],
        4 => ['login' => 'joe', 'capabilities' => ['author' => true, 'event_manager' => true, 'moderate_comments' => true]],
    ]));
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->root);
});

it('classifies stored entries as roles, capabilities or dead roles', function (): void {
    $classifier = new StoredEntryClassifier;

    expect($classifier->classify('author', $this->wpRoles->roles))->toBe(StoredEntryClassifier::ROLE)
        ->and($classifier->classify('moderate_comments', $this->wpRoles->roles))->toBe(StoredEntryClassifier::CAPABILITY)
        ->and($classifier->classify('event_manager', $this->wpRoles->roles))->toBe(StoredEntryClassifier::DEAD);
});

describe('pollora:roles:prune', function (): void {
    it('refuses to leave a user with no role', function (): void {
        [$code, $output] = runWritingRoleCommand(new RolePruneCommand, $this->app, ['--force' => true]);

        expect($code)->toBe(1)
            ->and($output)->toContain('1 user(s) would be left with no role')
            ->and($this->store->writes)->toBe([]);
    });

    it('only shows the changes without --force', function (): void {
        [$code, $output] = runWritingRoleCommand(new RolePruneCommand, $this->app, ['--reassign' => 'subscriber']);

        expect($code)->toBe(0)
            ->and($output)->toContain('Dry run')->toContain('jane')->toContain('old_manager')
            ->and($this->store->writes)->toBe([]);
    });

    it('takes the dead role off users, reassigns the roleless and deletes the stored copy', function (): void {
        [$code] = runWritingRoleCommand(new RolePruneCommand, $this->app, ['--reassign' => 'subscriber', '--force' => true]);

        expect($code)->toBe(0)
            ->and($this->store->writes)->toBe([
                'user 3: remove event_manager',
                'user 3: add subscriber',
                'user 4: remove event_manager',
                'roles: administrator, author, subscriber, venue_staff',
            ]);
    });

    it('refuses a reassign role that does not exist', function (): void {
        [$code, $output] = runWritingRoleCommand(new RolePruneCommand, $this->app, ['--reassign' => 'ghost']);

        expect($code)->toBe(1)->and($output)->toContain('WordPress has no role "ghost" to reassign.');
    });
});

describe('pollora:roles:import', function (): void {
    it('writes a #[Role] class with the differences from the inherited role', function (): void {
        [$code] = runWritingRoleCommand(new RoleImportCommand(new Filesystem), $this->app, ['role' => 'venue_staff', '--inherits' => 'author']);
        $class = (string) file_get_contents($this->root.'/app/Cms/Roles/VenueStaff.php');

        expect($code)->toBe(0)
            ->and($class)->toContain('namespace App\\Cms\\Roles;')
            ->toContain("#[Role('venue_staff', label: 'Venue staff', inherits: 'author', allowSensitive: true)]")
            ->toContain("#[Grants(\n    'edit_users',\n    'scan_tickets',\n)]")
            ->toContain("#[Without(\n    'publish_posts',\n)]")
            ->toContain('let a user take over the site: edit_users.')
            ->toContain('left out, since a role only grants or removes: spam.')
            ->toContain('final class VenueStaff');
    });

    it('refuses a core role, an unknown one and one the code already declares', function (string $role, string $message): void {
        $this->registry->addRole((new RoleDefinitionBuilder(new CapabilityOwnerReader))->build(EventManager::class));
        $this->store->stored['event_manager'] = ['name' => 'Event manager', 'capabilities' => []];

        [$code, $output] = runWritingRoleCommand(new RoleImportCommand(new Filesystem), $this->app, ['role' => $role]);

        expect($code)->toBe(1)->and($output)->toContain($message);
    })->with([
        'core' => ['editor', '"editor" is a core role: change it with a #[ModifyRole(\'editor\')] class instead.'],
        'unknown' => ['ghost', 'No role "ghost" is stored in the database.'],
        'declared' => ['event_manager', 'The role "event_manager" is already declared in the code.'],
    ]);
});

describe('pollora:roles:dump', function (): void {
    it('shows the roles it would add, change or remove, and writes them with --force', function (): void {
        $this->wpRoles->roles['event_manager'] = ['name' => 'Event manager', 'capabilities' => ['read' => true], '_pollora' => ['managed' => true]];

        [, $dryRun] = runWritingRoleCommand(new RoleDumpCommand, $this->app);
        $nothingWritten = $this->store->writes;
        [$code] = runWritingRoleCommand(new RoleDumpCommand, $this->app, ['--force' => true]);

        expect($dryRun)->toContain('event_manager')->toContain('added')->toContain('old_manager')->toContain('removed')
            ->and($nothingWritten)->toBe([])
            ->and($code)->toBe(0)
            ->and($this->store->stored)->toBe($this->wpRoles->roles);
    });

    it('has nothing to write when the database holds the roles, markers aside', function (): void {
        $this->store->stored = $this->wpRoles->roles;
        $this->store->stored['author']['_pollora'] = ['granted' => []];

        [, $output] = runWritingRoleCommand(new RoleDumpCommand, $this->app);

        expect($output)->toContain('already holds the roles');
    });
});

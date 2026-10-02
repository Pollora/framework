<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Console\Application;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\DTO\InstallationConfig;
use Pollora\Services\WordPress\Installation\InstallationService;
use Pollora\WordPress\Commands\LaunchPadInstallCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Run pollora:install against a console application where pollora:make:theme and
 * migrate are replaced by commands recording the input they receive.
 *
 * @param  array<string, mixed>  $parameters
 * @return array{exit: int, theme: array<string, mixed>|null, migrate: array<string, mixed>|null, config: InstallationConfig|null, output: string}
 */
function runInstallCommand(array $parameters, bool $interactive, int $migrateExit = 0): array
{
    $config = null;
    $installation = Mockery::mock(InstallationService::class);
    $installation->shouldReceive('isInstalled')->andReturn(false);
    $installation->shouldReceive('install')->once()->andReturnUsing(function (InstallationConfig $given) use (&$config): void {
        $config = $given;
    });

    $database = Mockery::mock(DatabaseService::class);
    $database->shouldReceive('isConfigured')->andReturn(true);

    $recorder = new #[Signature('pollora:make:theme {name?} {--theme-author=} {--activate}')] class extends Command
    {
        /** @var array<string, mixed>|null */
        public ?array $received = null;

        public function handle(): int
        {
            $this->received = [
                'name' => $this->argument('name'),
                'interactive' => $this->input->isInteractive(),
                'activate' => $this->option('activate'),
            ];

            return self::SUCCESS;
        }
    };

    $migrate = new #[Signature('migrate {--force}')] class extends Command
    {
        public int $exit = self::SUCCESS;

        /** @var array<string, mixed>|null */
        public ?array $received = null;

        public function handle(): int
        {
            $this->received = ['force' => $this->option('force')];

            return $this->exit;
        }
    };
    $migrate->exit = $migrateExit;

    // Laravel commands ask their container whether unit tests are running
    $container = new class extends Container
    {
        public function runningUnitTests(): bool
        {
            return false;
        }

        public function isLocal(): bool
        {
            return false;
        }

        public function basePath(string $path = ''): string
        {
            // DDEV and Docker keep every project in /var/www/html
            return '/var/www/html'.($path !== '' ? '/'.$path : '');
        }
    };
    $container->instance('config', new Repository(['app' => ['url' => 'https://acme-site.ddev.site']]));
    // handleError() resolves app() to check the environment
    $previousContainer = Container::getInstance();
    Container::setInstance($container);
    $application = new Application($container, new Dispatcher($container), 'testing');
    $application->setAutoExit(false);

    foreach ([new LaunchPadInstallCommand($installation, $database), $recorder, $migrate] as $command) {
        $command->setLaravel($container);
        $application->addCommand($command);
    }

    $input = new ArrayInput(['command' => 'pollora:install', ...$parameters]);
    $input->setInteractive($interactive);

    try {
        $output = new BufferedOutput;
        $exit = $application->find('pollora:install')->run($input, $output);
    } finally {
        Container::setInstance($previousContainer);
    }

    return ['exit' => $exit, 'theme' => $recorder->received, 'migrate' => $migrate->received, 'config' => $config, 'output' => $output->fetch()];
}

describe('pollora:install theme generation', function (): void {
    beforeEach(function (): void {
        Brain\Monkey\Functions\when('admin_url')->justReturn('https://example.test/wp-admin/');

        $this->installOptions = [
            '--install' => true,
            '--title' => 'Pollora',
            '--description' => 'Test',
            '--admin-user' => 'admin',
            '--admin-email' => 'admin@example.com',
            '--admin-password' => 'secret123',
            '--locale' => 'en_US',
            '--public' => 'false',
        ];
    });

    it('generates the default theme when it cannot prompt', function (): void {
        $result = runInstallCommand($this->installOptions, interactive: false);

        expect($result['exit'])->toBe(0)
            ->and($result['theme'])->toBe(['name' => 'default', 'interactive' => false, 'activate' => true]);
    });

    it('generates the theme named by --theme', function (): void {
        $result = runInstallCommand([...$this->installOptions, '--theme' => 'acme'], interactive: false);

        expect($result['theme'])->toBe(['name' => 'acme', 'interactive' => false, 'activate' => true]);
    });

    it('leaves the theme name to the prompt when interactive', function (): void {
        $result = runInstallCommand($this->installOptions, interactive: true);

        expect($result['theme']['name'])->toBeNull();
    });
});

describe('pollora:install migrations', function (): void {
    beforeEach(function (): void {
        Brain\Monkey\Functions\when('admin_url')->justReturn('https://example.test/wp-admin/');

        $this->installOptions = [
            '--install' => true,
            '--title' => 'Pollora',
            '--description' => 'Test',
            '--admin-user' => 'admin',
            '--admin-email' => 'admin@example.com',
            '--admin-password' => 'secret123',
            '--locale' => 'en_US',
            '--public' => 'false',
        ];
    });

    it('forces migrations so production does not cancel them without a prompt', function (): void {
        $result = runInstallCommand($this->installOptions, interactive: false);

        expect($result['migrate'])->toBe(['force' => true]);
    });

    it('fails instead of reporting success when migrations fail', function (): void {
        $result = runInstallCommand($this->installOptions, interactive: false, migrateExit: 1);

        expect($result['exit'])->toBe(1)
            ->and($result['theme'])->toBeNull();
    });
});

describe('pollora:install without prompts', function (): void {
    beforeEach(function (): void {
        Brain\Monkey\Functions\when('admin_url')->justReturn('https://acme-site.ddev.site/wp-admin/');
    });

    it('installs with working defaults instead of failing on the first required prompt', function (): void {
        // `pollora new --ddev --no-interaction` ran it with no option and no
        // terminal, and the install stopped on "Site title is required".
        $result = runInstallCommand(['--install' => true], interactive: false);

        expect($result['exit'])->toBe(0)
            ->and($result['config'])->toBeInstanceOf(InstallationConfig::class)
            ->and($result['config']->title)->toBe('Acme Site')
            ->and($result['config']->adminUser)->toBe('admin')
            ->and($result['config']->adminEmail)->toBe('admin@acme-site.ddev.site')
            ->and(strlen($result['config']->adminPassword))->toBe(20)
            ->and($result['config']->locale)->toBe('en_US')
            ->and($result['config']->isPublic)->toBeFalse();
    });

    it('shows a generated password once, even in --install mode', function (): void {
        $result = runInstallCommand(['--install' => true], interactive: false);

        expect($result['output'])->toContain($result['config']->adminPassword);
    });

    it('keeps every option it is given', function (): void {
        $result = runInstallCommand([
            '--install' => true,
            '--title' => 'Pollora',
            '--admin-user' => 'olivier',
            '--admin-email' => 'dev@example.org',
            '--admin-password' => 'secret123',
            '--locale' => 'fr_FR',
            '--public' => 'true',
        ], interactive: false);

        expect($result['config']->title)->toBe('Pollora')
            ->and($result['config']->adminUser)->toBe('olivier')
            ->and($result['config']->adminEmail)->toBe('dev@example.org')
            ->and($result['config']->adminPassword)->toBe('secret123')
            ->and($result['config']->locale)->toBe('fr_FR')
            ->and($result['config']->isPublic)->toBeTrue()
            ->and($result['output'])->not->toContain('secret123');
    });
});

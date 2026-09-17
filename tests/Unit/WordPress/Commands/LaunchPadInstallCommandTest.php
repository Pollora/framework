<?php

declare(strict_types=1);

use Illuminate\Console\Application;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\InstallationService;
use Pollora\WordPress\Commands\LaunchPadInstallCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Run pollora:install against a console application where pollora:make:theme and
 * migrate are replaced by commands recording the input they receive.
 *
 * @param  array<string, mixed>  $parameters
 * @return array{exit: int, theme: array<string, mixed>|null}
 */
function runInstallCommand(array $parameters, bool $interactive): array
{
    $installation = Mockery::mock(InstallationService::class);
    $installation->shouldReceive('isInstalled')->andReturn(false);
    $installation->shouldReceive('install')->once();

    $database = Mockery::mock(DatabaseService::class);
    $database->shouldReceive('isConfigured')->andReturn(true);

    $recorder = new #[Signature('pollora:make:theme {name?} {--theme-author=}')] class extends Command
    {
        /** @var array<string, mixed>|null */
        public ?array $received = null;

        public function handle(): int
        {
            $this->received = [
                'name' => $this->argument('name'),
                'interactive' => $this->input->isInteractive(),
            ];

            return self::SUCCESS;
        }
    };

    $migrate = new #[Signature('migrate')] class extends Command
    {
        public function handle(): int
        {
            return self::SUCCESS;
        }
    };

    // Laravel commands ask their container whether unit tests are running
    $container = new class extends Container
    {
        public function runningUnitTests(): bool
        {
            return false;
        }
    };
    $application = new Application($container, new Dispatcher($container), 'testing');
    $application->setAutoExit(false);

    foreach ([new LaunchPadInstallCommand($installation, $database), $recorder, $migrate] as $command) {
        $command->setLaravel($container);
        $application->addCommand($command);
    }

    $input = new ArrayInput(['command' => 'pollora:install', ...$parameters]);
    $input->setInteractive($interactive);

    $exit = $application->find('pollora:install')->run($input, new BufferedOutput);

    return ['exit' => $exit, 'theme' => $recorder->received];
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
            ->and($result['theme'])->toBe(['name' => 'default', 'interactive' => false]);
    });

    it('generates the theme named by --theme', function (): void {
        $result = runInstallCommand([...$this->installOptions, '--theme' => 'acme'], interactive: false);

        expect($result['theme'])->toBe(['name' => 'acme', 'interactive' => false]);
    });

    it('leaves the theme name to the prompt when interactive', function (): void {
        $result = runInstallCommand($this->installOptions, interactive: true);

        expect($result['theme']['name'])->toBeNull();
    });
});

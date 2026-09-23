<?php

declare(strict_types=1);

use Illuminate\Console\Application;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\WordPress\Commands\LaunchPadSetupCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Run pollora:env:setup against a console application, with the database
 * service answering whatever the test needs and interactivity set explicitly.
 *
 * @param  array<string, mixed>  $parameters
 * @return array{exit: int, output: string}
 */
function runSetupCommand(bool $configured, bool $interactive, array $parameters = []): array
{
    $database = Mockery::mock(DatabaseService::class);
    $database->shouldReceive('isConfigured')->andReturn($configured);

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
    };

    $previousContainer = Container::getInstance();
    Container::setInstance($container);

    $application = new Application($container, new Dispatcher($container), 'testing');
    $application->setAutoExit(false);

    $command = new LaunchPadSetupCommand($database);
    $command->setLaravel($container);

    $application->addCommand($command);

    $input = new ArrayInput(['command' => 'pollora:env:setup', ...$parameters]);
    $input->setInteractive($interactive);

    $output = new BufferedOutput;

    try {
        $exit = $application->find('pollora:env:setup')->run($input, $output);
    } finally {
        Container::setInstance($previousContainer);
    }

    return ['exit' => $exit, 'output' => $output->fetch()];
}

describe('pollora:env:setup without a terminal', function (): void {
    it('does not prompt, and lets composer finish', function (): void {
        // The regression this pins: the command is wired to composer's
        // post-autoload-dump hook, so it runs during `composer install` and
        // `composer update` in containers, deployments and CI. When the
        // database was not reachable it reached for Laravel Prompts, which
        // throws where there is no terminal — and composer exited 1 reporting
        // a prompting problem rather than a database one.
        $result = runSetupCommand(configured: false, interactive: false);

        expect($result['exit'])->toBe(0);
    });

    it('says what is wrong and what to run', function (): void {
        $result = runSetupCommand(configured: false, interactive: false);

        expect($result['output'])
            ->toContain('database is not reachable')
            ->toContain('php artisan pollora:env:setup');
    });

    it('still reports a configured database as nothing to do', function (): void {
        $result = runSetupCommand(configured: true, interactive: false);

        expect($result['exit'])->toBe(0)
            ->and($result['output'])->toContain('already configured');
    });

    it('keeps --install quiet about a database that is already configured', function (): void {
        $result = runSetupCommand(configured: true, interactive: false, parameters: ['--install' => true]);

        expect($result['exit'])->toBe(0)
            ->and($result['output'])->not->toContain('already configured');
    });
});

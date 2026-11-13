<?php

declare(strict_types=1);

use Pollora\Support\NpmRunner;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * An NpmRunner that records the commands instead of starting npm.
 */
class PolloraRecordingNpmRunner extends NpmRunner
{
    /** @var array<int, array<int, string>> */
    public array $commands = [];

    protected function runCommand(array $command): void
    {
        $this->commands[] = $command;
    }
}

/**
 * Runs a command through the real runner, with PHP standing in for npm.
 *
 * @param  array<int, string>  $command
 */
function pollora_run_npm_command(string $directory, array $command): string
{
    $runner = new NpmRunner($directory);
    $method = new ReflectionMethod($runner, 'runCommand');

    ob_start();

    try {
        $method->invoke($runner, $command);
    } finally {
        $output = (string) ob_get_clean();
    }

    return $output;
}

describe('NpmRunner commands', function (): void {
    it('installs the dependencies with npm install', function (): void {
        $runner = new PolloraRecordingNpmRunner('/theme');

        $runner->install();

        expect($runner->commands)->toBe([['npm', 'install']]);
    });

    it('builds the assets with npm run build', function (): void {
        $runner = new PolloraRecordingNpmRunner('/theme');

        $runner->build();

        expect($runner->commands)->toBe([['npm', 'run', 'build']]);
    });

    it('lets the calls be chained and keeps their order', function (): void {
        $runner = new PolloraRecordingNpmRunner('/theme');

        expect($runner->install())->toBe($runner)
            ->and($runner->build())->toBe($runner)
            ->and($runner->commands)->toBe([['npm', 'install'], ['npm', 'run', 'build']]);
    });
});

describe('NpmRunner process', function (): void {
    beforeEach(function (): void {
        $this->directory = sys_get_temp_dir().'/pollora-npm-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    });

    afterEach(function (): void {
        rmdir($this->directory);
    });

    it('runs the command in the working directory and prints its output', function (): void {
        $output = pollora_run_npm_command($this->directory, [PHP_BINARY, '-r', 'echo getcwd();']);

        expect(realpath($output))->toBe(realpath($this->directory));
    });

    it('throws when the command fails, with its exit code and output', function (): void {
        $run = fn () => pollora_run_npm_command($this->directory, [PHP_BINARY, '-r', 'fwrite(STDERR, "build broke"); exit(3);']);

        expect($run)->toThrow(ProcessFailedException::class, 'build broke');
    });

    it('does not throw when the command succeeds', function (): void {
        $output = pollora_run_npm_command($this->directory, [PHP_BINARY, '-r', 'echo "built";']);

        expect($output)->toBe('built');
    });
});

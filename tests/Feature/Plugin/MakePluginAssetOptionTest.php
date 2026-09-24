<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Pollora\Plugin\UI\Console\MakePluginCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Whether pollora:make:plugin includes the asset files, for a command line.
 */
function makePluginIncludesAssets(Application $app, array $arguments): bool
{
    $command = $app->make(MakePluginCommand::class);
    $command->setLaravel($app);

    $input = new ArgvInput(['artisan', 'acme', ...$arguments]);
    $input->bind($command->getDefinition());
    $input->setInteractive(false);

    (new ReflectionProperty($command, 'input'))->setValue($command, $input);
    (new ReflectionMethod($command, 'initialize'))->invoke($command, $input, new BufferedOutput);

    return (new ReflectionMethod($command, 'shouldIncludeAssets'))->invoke($command);
}

describe('pollora:make:plugin --asset', function (): void {
    it('includes the assets for --asset given alone', function (): void {
        // It used to mean the opposite: a flag with no value read as null,
        // which the missing-option defaults turned into "false".
        expect(makePluginIncludesAssets($this->app, ['--asset']))->toBeTrue();
    });

    it('includes the assets for --asset=true', function (): void {
        expect(makePluginIncludesAssets($this->app, ['--asset=true']))->toBeTrue();
    });

    it('leaves the assets out for --asset=false', function (): void {
        expect(makePluginIncludesAssets($this->app, ['--asset=false']))->toBeFalse();
    });

    it('still gives the other options their defaults without a terminal', function (): void {
        $command = $this->app->make(MakePluginCommand::class);
        $command->setLaravel($this->app);

        $input = new ArgvInput(['artisan', 'acme', '--asset']);
        $input->bind($command->getDefinition());
        $input->setInteractive(false);

        (new ReflectionMethod($command, 'initialize'))->invoke($command, $input, new BufferedOutput);

        expect($input->getOption('plugin-version'))->toBe('1.0.0');
    });

    it('leaves the assets out when --asset is not given', function (): void {
        expect(makePluginIncludesAssets($this->app, []))->toBeFalse();
    });
});

<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Config\Repository;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Pollora\Theme\Application\Services\ThemeAvailability;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Models\ThemeMetadata;
use Pollora\Theme\UI\Console\MakeThemeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Which case activates the generated theme: a site with no theme gets it, a site with one
 * keeps its own unless told otherwise, and --activate / --no-activate settle it.
 */
function activationCommand(array $options, bool $themeMissing): MakeThemeCommand
{
    $command = new MakeThemeCommand(new Repository, new Filesystem);
    $command->setLaravel(app());

    $registrar = Mockery::mock(ThemeRegistrarInterface::class);
    $registrar->shouldReceive('getActiveTheme')->andReturn($themeMissing ? null : Mockery::mock(ThemeModuleInterface::class));
    $container = new Container;
    $container->instance(ThemeRegistrarInterface::class, $registrar);

    app()->instance(ThemeAvailability::class, new ThemeAvailability($container));

    $input = new ArrayInput(['name' => 'my-journal', ...$options], $command->getDefinition());
    $input->setInteractive(false);

    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, new BufferedOutput));

    (new ReflectionProperty($command, 'theme'))->setValue($command, new ThemeMetadata('my-journal', '/tmp/themes'));

    return $command;
}

function activates(MakeThemeCommand $command): bool
{
    $switched = [];
    Functions\when('switch_theme')->alias(function (string $stylesheet) use (&$switched): void {
        $switched[] = $stylesheet;
    });
    // Writing the option directly is activating too, as the command once did.
    Functions\when('update_option')->alias(function (string $option, mixed $value) use (&$switched): bool {
        if ($option === 'stylesheet') {
            $switched[] = $value;
        }

        return true;
    });

    (fn () => $this->promptAndSetActiveTheme())->call($command);

    return $switched === ['my-journal'];
}

beforeEach(function (): void {
    Functions\when('get_stylesheet')->justReturn('twentytwentyfive');
    Functions\when('get_stylesheet_directory')->justReturn('/nope');
});

it('activates the theme on a site that has none, without asking', function (): void {
    expect(activates(activationCommand([], themeMissing: true)))->toBeTrue();
});

it('keeps the active theme of a site that has one, when nobody answers', function (): void {
    expect(activates(activationCommand([], themeMissing: false)))->toBeFalse();
});

it('activates with --activate, even over another theme', function (): void {
    expect(activates(activationCommand(['--activate' => true], themeMissing: false)))->toBeTrue();
});

it('leaves the site alone with --no-activate, even when it has no theme', function (): void {
    expect(activates(activationCommand(['--no-activate' => true], themeMissing: true)))->toBeFalse();
});

it('does nothing when the generated theme is already the active one', function (): void {
    Functions\when('get_stylesheet')->justReturn('my-journal');

    expect(activates(activationCommand(['--activate' => true], themeMissing: false)))->toBeFalse();
});

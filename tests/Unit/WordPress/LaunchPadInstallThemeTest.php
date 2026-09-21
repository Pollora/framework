<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Pollora\WordPress\Commands\LaunchPadInstallCommand;

/**
 * `pollora:install` installed WordPress and then exited 1.
 *
 * Its last step scaffolds a theme, and it called `pollora:make-theme` with no
 * arguments. A nested call inherits only an explicit --no-interaction flag,
 * never a non-interactive input Symfony worked out on its own — CI, a piped
 * stdin, a provisioning script — so the sub-command tried to prompt for a name
 * it could not ask for:
 *
 *   Not enough arguments (missing: "name").
 *
 * The site was already installed by then. The caller got a failure exit code
 * for a successful install, and a message naming a command they never typed.
 *
 * Measured on a disposable site running v13.4.0 before this was written.
 */
describe('pollora:install theme step', function (): void {
    it('accepts a theme name', function (): void {
        $command = new ReflectionClass(LaunchPadInstallCommand::class);
        $signature = $command->getProperty('signature');
        $signature->setAccessible(true);

        expect($signature->getValue($command->newInstanceWithoutConstructor()))
            ->toContain('--theme=');
    });

    it('has a default theme to fall back on', function (): void {
        // Without one there is nothing to hand the sub-command when the caller
        // named no theme and cannot be asked, which is the whole failure.
        $constant = new ReflectionClassConstant(LaunchPadInstallCommand::class, 'DEFAULT_THEME');

        expect($constant->getValue())->toBe('default');
    });

    it('names a theme and passes --no-interaction when it cannot prompt', function (): void {
        $arguments = installThemeArgumentsFor(interactive: false, theme: null);

        expect($arguments)->toBe(['name' => 'default', '--no-interaction' => true]);
    });

    it('prefers the theme the caller asked for', function (): void {
        $arguments = installThemeArgumentsFor(interactive: false, theme: 'apiary');

        expect($arguments)->toBe(['name' => 'apiary', '--no-interaction' => true]);
    });

    it('leaves an interactive run free to prompt', function (): void {
        // Someone running the command by hand and naming no theme should still
        // be asked, the way they always were.
        expect(installThemeArgumentsFor(interactive: true, theme: null))->toBe([]);
    });

    it('still honours a named theme when interactive', function (): void {
        expect(installThemeArgumentsFor(interactive: true, theme: 'apiary'))->toBe(['name' => 'apiary']);
    });
});

/**
 * Drive installTheme() and capture what it would pass to make-theme.
 *
 * The method is private and its collaborators are the whole command, so it is
 * reached through a subclass that records the call instead of making it.
 *
 * @return array<string, mixed>
 */
function installThemeArgumentsFor(bool $interactive, ?string $theme): array
{
    $command = new class extends LaunchPadInstallCommand
    {
        /** @var array<string, mixed> */
        public array $captured = [];

        public bool $interactive = true;

        public ?string $theme = null;

        public function __construct()
        {
            // The parent constructor wants services this test has no use for.
        }

        public function option($key = null): mixed
        {
            return $key === 'theme' ? $this->theme : null;
        }

        public function call($command, array $arguments = []): int
        {
            $this->captured = $arguments;

            return 0;
        }

        public function scaffoldTheme(): void
        {
            $method = new ReflectionMethod(LaunchPadInstallCommand::class, 'installTheme');
            $method->setAccessible(true);
            $method->invoke($this);
        }
    };

    $command->interactive = $interactive;
    $command->theme = $theme;

    $input = new ReflectionProperty(Command::class, 'input');
    $input->setAccessible(true);
    $input->setValue($command, new class($interactive)
    {
        public function __construct(private readonly bool $interactive) {}

        public function isInteractive(): bool
        {
            return $this->interactive;
        }
    });

    $command->scaffoldTheme();

    return $command->captured;
}

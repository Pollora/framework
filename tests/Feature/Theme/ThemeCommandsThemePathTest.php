<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Pollora\Theme\UI\Console\RemoveThemeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * The shipped configuration defines theme.path (there is no theme.directory), so
 * the console commands built on BaseThemeCommand must follow it.
 */
function themePathRemoveCommand(?Repository $config = null): RemoveThemeCommand
{
    $command = new class($config ?? resolve('config'), new Filesystem) extends RemoveThemeCommand
    {
        public function confirm($question, $default = false): bool
        {
            return true;
        }

        public function themesPath(): string
        {
            return $this->getThemesPath();
        }
    };
    $command->setDefinition((new RemoveThemeCommand(resolve('config'), new Filesystem))->getDefinition());
    $command->setLaravel(app());

    return $command;
}

beforeEach(function (): void {
    $this->themesDir = sys_get_temp_dir().'/pollora-theme-path-'.bin2hex(random_bytes(6));
    mkdir($this->themesDir, 0777, true);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->themesDir);
});

describe('theme console commands and theme.path', function (): void {
    it('resolves the themes directory from a custom theme.path', function (): void {
        config(['theme.path' => $this->themesDir]);

        expect(themePathRemoveCommand()->themesPath())->toBe($this->themesDir);
    });

    it('deletes a theme from the custom theme.path rather than from base_path(themes)', function (): void {
        config(['theme.path' => $this->themesDir]);
        mkdir($this->themesDir.'/demo/resources', 0777, true);
        file_put_contents($this->themesDir.'/demo/style.css', '');

        $input = new ArrayInput(['name' => 'demo']);
        $input->setInteractive(false);

        $output = new BufferedOutput;
        $code = themePathRemoveCommand()->run($input, $output);

        expect($code)->toBe(0)
            ->and($output->fetch())->toContain('Theme "demo" has been removed successfully.')
            ->and(is_dir($this->themesDir.'/demo'))->toBeFalse();
    });

    it('falls back to base_path(themes) when theme.path is not configured', function (): void {
        expect(themePathRemoveCommand(new Repository)->themesPath())->toBe(base_path('themes'));
    });
});

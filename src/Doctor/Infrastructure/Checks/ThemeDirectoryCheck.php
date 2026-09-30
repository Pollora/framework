<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\Infrastructure\Support\ActiveTheme;

/**
 * The theme directory is not a symbolic link to a directory of another name.
 *
 * The Vite build names its output folder after the theme directory, and Node
 * resolves symlinks: a theme linked to ~/repos/theme-buzz builds into
 * public/build/theme/theme-buzz while the site asks public/build/theme/buzz, so
 * every asset and font URL answers 404.
 */
final readonly class ThemeDirectoryCheck implements CheckInterface
{
    public function id(): string
    {
        return 'theme-directory';
    }

    public function label(): string
    {
        return 'Theme directory';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        $directory = ActiveTheme::directory();

        if ($directory === null || ! file_exists($directory)) {
            return CheckResult::skipped('No active theme directory.');
        }

        $target = realpath($directory);

        if (is_link($directory) && $target !== false && basename($target) !== basename($directory)) {
            return CheckResult::error(
                sprintf('The theme is a symlink to "%s": its build goes to public/build/theme/%s, the site looks in public/build/theme/%s.', basename($target), basename($target), basename($directory)),
                [$directory.' → '.$target],
                sprintf('Use a real copy of the theme in %s (rsync), or name the link\'s target "%s"', ActiveTheme::relativeDirectory(), basename($directory)),
            );
        }

        return CheckResult::ok("The build and the site agree on the theme's directory name.");
    }
}

<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\Infrastructure\Support\ProjectModules;

/**
 * No theme, plugin or module is a symbolic link to a directory of another name.
 *
 * Vite names the build folder after the directory, and Node resolves symlinks: a
 * theme linked to ~/repos/theme-buzz builds into public/build/theme/theme-buzz
 * while the site asks public/build/theme/buzz, so every asset URL answers 404.
 */
final readonly class SymlinkedDirectoryCheck implements CheckInterface
{
    public function __construct(private ProjectModules $modules) {}

    public function id(): string
    {
        return 'symlinked-directories';
    }

    public function label(): string
    {
        return 'Symlinked theme, plugin and module directories';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        $problems = [];

        foreach ($this->modules->all() as $module) {
            $target = realpath($module->root);

            if (is_link($module->root) && $target !== false && basename($target) !== basename($module->root)) {
                $problems[] = sprintf('%s: %s → %s, so its build goes to a "%s" folder, not "%s"', $module->label(), $module->relativeRoot(), $target, basename($target), basename($module->root));
            }
        }

        if ($problems !== []) {
            return CheckResult::error(
                sprintf('%d directory(ies) are symlinks under another name: their assets are built where the site does not look.', count($problems)),
                $problems,
                "Use a real copy (rsync) at that path, or give the link's target the same name",
            );
        }

        return CheckResult::ok('No directory is a symlink under another name.');
    }
}

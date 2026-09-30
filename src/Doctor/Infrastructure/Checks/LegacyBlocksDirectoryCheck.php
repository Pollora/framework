<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\Infrastructure\Support\ProjectModules;

/**
 * No block still lives in resources/blocks.
 *
 * Blocks moved to resources/views/blocks in 13.32. The old folder still loads,
 * with a deprecation logged on every request, and stops loading in v15: those
 * blocks will then vanish from the editor and the pages without an error.
 */
final readonly class LegacyBlocksDirectoryCheck implements CheckInterface
{
    public function __construct(private ProjectModules $modules) {}

    public function id(): string
    {
        return 'legacy-blocks-directory';
    }

    public function label(): string
    {
        return 'Blocks in the legacy folder';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        $legacy = [];

        foreach ($this->modules->all() as $module) {
            $blocks = array_values(array_filter($module->files('resources/blocks', ['json']), static fn (string $file): bool => basename($file) === 'block.json'));

            if ($blocks !== []) {
                $legacy[] = sprintf('%s: %d block(s) in %s/resources/blocks', $module->label(), count($blocks), $module->relativeRoot());
            }
        }

        if ($legacy !== []) {
            return CheckResult::warning(
                sprintf('%d module(s) keep blocks in resources/blocks, which stops loading in v15.', count($legacy)),
                $legacy,
                'Move each block to resources/views/blocks/<slug>, and point vite.config.js at the new folder',
            );
        }

        return CheckResult::ok('Every block is in resources/views/blocks.');
    }
}

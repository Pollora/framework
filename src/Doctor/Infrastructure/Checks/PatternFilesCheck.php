<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\Infrastructure\Support\ActiveTheme;

/**
 * Every file in the theme's patterns/ is one WordPress registers.
 *
 * WordPress reads only .php files there: an .html pattern is ignored without a
 * warning. A .php file without a Title or a Slug is skipped too.
 */
final readonly class PatternFilesCheck implements CheckInterface
{
    public function id(): string
    {
        return 'pattern-files';
    }

    public function label(): string
    {
        return 'Theme pattern files';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        $directory = ActiveTheme::directory();

        if ($directory === null) {
            return CheckResult::skipped('WordPress is not loaded.');
        }

        if (! is_dir($directory.'/patterns')) {
            return CheckResult::ok('The theme has no patterns/ directory.');
        }

        $ignored = ActiveTheme::files('patterns', ['html']);
        $headerless = array_values(array_filter(
            ActiveTheme::files('patterns', ['php']),
            static fn (string $file): bool => ! ActiveTheme::patternHasHeader($directory.'/'.$file),
        ));

        if ($ignored !== [] || $headerless !== []) {
            return CheckResult::warning(
                sprintf('%d pattern file(s) are never registered by WordPress.', count($ignored) + count($headerless)),
                [
                    ...array_map(static fn (string $file): string => $file.': WordPress reads only .php files in patterns/', $ignored),
                    ...array_map(static fn (string $file): string => $file.': no Title or Slug in its header', $headerless),
                ],
                'Make each one a .php file whose header is a docblock with Title and Slug (/** Title: … Slug: my-theme/… */)',
            );
        }

        return CheckResult::ok('Every pattern file is one WordPress registers.');
    }
}

<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\Infrastructure\Support\ActiveTheme;

/**
 * WordPress's cached list of the theme's patterns/ files is current.
 *
 * WordPress caches that list: a pattern file added since does not exist for the
 * editor or for a template that references it, until the cache expires or is
 * cleared. Theme development mode turns the cache off.
 */
final readonly class PatternCacheCheck implements CheckInterface
{
    public function id(): string
    {
        return 'pattern-cache';
    }

    public function label(): string
    {
        return 'Theme pattern cache';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if (! function_exists('wp_get_theme') || ActiveTheme::directory() === null) {
            return CheckResult::skipped('WordPress is not loaded.');
        }

        $directory = ActiveTheme::directory();

        // A file without a Title or a Slug is never cached: the pattern-files check names it.
        $files = array_values(array_map(
            static fn (string $file): string => substr($file, strlen('patterns/')),
            array_filter(ActiveTheme::files('patterns', ['php']), static fn (string $file): bool => ActiveTheme::patternHasHeader($directory.'/'.$file)),
        ));

        if ($files === []) {
            return CheckResult::ok('The theme has no pattern files.');
        }

        if (function_exists('wp_is_development_mode') && wp_is_development_mode('theme')) {
            return CheckResult::ok('Theme development mode is on: WordPress reads patterns/ on every request.');
        }

        $theme = wp_get_theme();
        $cached = method_exists($theme, 'get_block_patterns') ? array_keys((array) $theme->get_block_patterns()) : $files;
        $missing = array_values(array_diff($files, $cached));

        if ($missing !== []) {
            return CheckResult::warning(
                sprintf("%d pattern file(s) are missing from WordPress's cached list: they do not exist for the editor or the templates.", count($missing)),
                array_map(static fn (string $file): string => 'patterns/'.$file, $missing),
                "wp eval 'wp_get_theme()->delete_pattern_cache();' (or set WP_DEVELOPMENT_MODE=theme while developing)",
            );
        }

        return CheckResult::ok("WordPress's cached list matches patterns/.");
    }
}

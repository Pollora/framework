<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\Infrastructure\Support\ActiveTheme;

/**
 * Every block of the active theme is registered in a web request.
 *
 * Up to v13.32.0-beta.7 blocks were registered under WP-CLI only: the console
 * saw them all while the editor, the page and the REST API had none. That is
 * why this check runs in Site Health — an administrator's web request — and
 * never in the console, where it would pass the broken site.
 */
final readonly class BlockRegistrationCheck implements CheckInterface
{
    public function id(): string
    {
        return 'block-registration';
    }

    public function label(): string
    {
        return 'Theme blocks registered';
    }

    public function runsIn(): array
    {
        return [RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if (! class_exists(\WP_Block_Type_Registry::class)) {
            return CheckResult::skipped('WordPress is not loaded.');
        }

        $directory = (string) ActiveTheme::directory();
        $registry = \WP_Block_Type_Registry::get_instance();
        $declared = [];
        $missing = [];

        foreach (ActiveTheme::files('resources/views/blocks', ['json']) as $file) {
            if (basename($file) !== 'block.json') {
                continue;
            }

            $name = json_decode((string) file_get_contents($directory.'/'.$file), true)['name'] ?? null;

            if (! is_string($name)) {
                $missing[] = $file.': no "name" in block.json';

                continue;
            }

            $declared[] = $name;

            if (! $registry->is_registered($name)) {
                $missing[] = $name.' ('.$file.')';
            }
        }

        if ($missing !== []) {
            return CheckResult::error(
                sprintf('%d block(s) of the theme are not registered: the editor and the pages do not have them.', count($missing)),
                $missing,
                "Check storage/logs/laravel.log for the block's registration error; a dynamic block needs a valid block.json and its render.blade.php",
            );
        }

        return CheckResult::ok(sprintf('%d block(s) of the theme are registered.', count($declared)));
    }
}

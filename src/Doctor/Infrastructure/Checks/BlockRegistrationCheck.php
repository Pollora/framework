<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\Infrastructure\Support\ProjectModules;

/**
 * Every block of the theme, the Pollora plugins and the modules is registered in a web request.
 *
 * Up to v13.32.0-beta.7 blocks were registered under WP-CLI only: the console saw
 * them all while the editor, the page and the REST API had none. That is why this
 * check runs in Site Health — an administrator's web request — and never in the
 * console, where it would pass the broken site.
 */
final readonly class BlockRegistrationCheck implements CheckInterface
{
    /** Where Pollora looks for blocks, the legacy location included until v15. */
    private const array DIRECTORIES = ['resources/views/blocks', 'resources/blocks'];

    public function __construct(private ProjectModules $modules) {}

    public function id(): string
    {
        return 'block-registration';
    }

    public function label(): string
    {
        return 'Blocks registered';
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

        $registry = \WP_Block_Type_Registry::get_instance();
        $registered = 0;
        $missing = [];

        foreach ($this->modules->all() as $module) {
            foreach (self::DIRECTORIES as $directory) {
                foreach ($module->files($directory, ['json']) as $file) {
                    if (basename($file) !== 'block.json') {
                        continue;
                    }

                    $name = json_decode((string) file_get_contents($module->root.'/'.$file), true)['name'] ?? null;

                    if (! is_string($name)) {
                        $missing[] = sprintf('%s: %s has no "name"', $module->label(), $file);
                    } elseif ($registry->is_registered($name)) {
                        $registered++;
                    } else {
                        $missing[] = sprintf('%s: %s (%s)', $module->label(), $name, $file);
                    }
                }
            }
        }

        if ($missing !== []) {
            return CheckResult::error(
                sprintf('%d block(s) are not registered: the editor and the pages do not have them.', count($missing)),
                $missing,
                "Check storage/logs/laravel.log for the block's registration error; a dynamic block needs a valid block.json and its render.blade.php",
            );
        }

        return CheckResult::ok(sprintf('%d block(s) registered.', $registered));
    }
}

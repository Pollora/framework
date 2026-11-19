<?php

declare(strict_types=1);

namespace Pollora\Console\Concerns;

use function Laravel\Prompts\text;

/**
 * "Delete then regenerate" for a scaffold command whose target directory already exists.
 *
 * Only `--force` gets here, and even then nothing is removed unless a person types the
 * exact name of the theme or plugin: the scaffold would otherwise run on top of the
 * existing files (the downloader empties parts of the target and the extraction folder).
 * The command expects `$files` (Filesystem), `$input`, `option()` and `error()`.
 */
trait ReplacesExistingDirectory
{
    /**
     * Decide whether the existing directory of a theme or plugin may be replaced, and remove it if so.
     *
     * @param  string  $kind  "Theme" or "Plugin", for the messages
     * @param  string  $name  The validated name; the target is exactly `<root>/<name>`
     * @param  string  $root  The themes (or plugins) directory
     * @param  bool  $active  Whether WordPress reports this theme or plugin as active
     * @return bool True when the directory is gone and the generation can go on as for a new one
     */
    protected function replaceExistingDirectory(string $kind, string $name, string $root, bool $active): bool
    {
        $this->error(sprintf('%s "%s" already exists.', $kind, $name));

        if (! $this->option('force')) {
            return false;
        }

        $target = $root.'/'.$name;
        $unsafe = $this->unsafeReplacementReason($name, $root, $target);

        if ($unsafe !== null) {
            $this->error(sprintf('Refusing to delete "%s": %s.', $target, $unsafe));

            return false;
        }

        if ($active) {
            $this->error(sprintf('%s "%s" is active: it is not deleted. Activate another one first.', $kind, $name));

            return false;
        }

        if (! $this->input->isInteractive()) {
            $this->error(sprintf('"%s" would be deleted and generated again: run this command interactively to confirm it. Nothing was deleted.', $name));

            return false;
        }

        $typed = text(
            label: sprintf('"%s" will be DELETED and generated again. Type the %s name to confirm', $target, strtolower($kind)),
            hint: 'Anything else cancels.',
        );

        if ($typed !== $name) {
            $this->error('The name does not match: nothing was deleted.');

            return false;
        }

        if (! $this->files->deleteDirectory($target) || is_dir($target)) {
            $this->error(sprintf('"%s" could not be deleted completely.', $target));

            return false;
        }

        return true;
    }

    /**
     * Why this target must not be deleted, or null when it is exactly a directory of the root.
     */
    private function unsafeReplacementReason(string $name, string $root, string $target): ?string
    {
        if ($name === '' || $name === '.' || str_contains($name, '..') || str_contains($name, '/') || str_contains($name, '\\')) {
            return 'the name is empty or contains a path separator or ".."';
        }

        if ($target !== $root.'/'.$name) {
            return 'the target is not <root>/<name>';
        }

        if (is_link($target)) {
            return 'it is a symbolic link';
        }

        if (! is_dir($target)) {
            return 'it is not a directory';
        }

        $realRoot = realpath($root);
        $realTarget = realpath($target);

        if ($realRoot === false || $realTarget === false) {
            return 'its real path cannot be resolved';
        }

        if ($realTarget === $realRoot || dirname($realTarget) !== $realRoot) {
            return 'it is not directly inside the root directory';
        }

        return null;
    }
}

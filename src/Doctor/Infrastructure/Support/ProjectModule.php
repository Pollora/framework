<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Support;

/**
 * The active theme, a Pollora plugin or an enabled Laravel module, as the checks see it:
 * where it lives and where Pollora reads its build.
 */
final readonly class ProjectModule
{
    public function __construct(
        /** theme, plugin or module */
        public string $type,
        public string $name,
        public string $root,
        public string $hotFile,
        /** relative to the public directory */
        public string $buildDirectory,
        public string $manifestPath = 'manifest.json',
    ) {}

    /** "theme buzz", "plugin acme-forms", "module BlocksDemo" */
    public function label(): string
    {
        return $this->type.' '.$this->name;
    }

    /** The root relative to the project, for a command a person can paste. */
    public function relativeRoot(): string
    {
        $base = rtrim(base_path(), '/').'/';

        return str_starts_with($this->root, $base) ? substr($this->root, strlen($base)) : $this->root;
    }

    public function manifest(): string
    {
        return public_path(trim($this->buildDirectory, '/').'/'.ltrim($this->manifestPath, '/'));
    }

    /**
     * Files under the module with one of the extensions, dependencies and builds left out.
     *
     * @param  list<string>  $extensions
     * @return list<string> paths relative to the root
     */
    public function files(string $subdirectory, array $extensions, int $limit = 5000): array
    {
        $directory = rtrim($this->root.'/'.$subdirectory, '/');

        if (! is_dir($directory)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            static fn (\SplFileInfo $file): bool => ! in_array($file->getFilename(), ['node_modules', 'vendor', '.git'], true),
        ));

        $files = [];

        foreach ($iterator as $file) {
            if (in_array(strtolower($file->getExtension()), $extensions, true)) {
                $files[] = substr($file->getPathname(), strlen($this->root) + 1);
            }

            if (count($files) >= $limit) {
                break;
            }
        }

        sort($files);

        return $files;
    }
}

<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Support\Str;

/**
 * The lean module Pollora generates, and the Laravel layers added on request.
 *
 * The tree mirrors the Pollora/module-default template: classes discovered in
 * app/, Blade blocks, a Vite build through @pollora/vite-config, no service
 * provider. `pollora:make:module` downloads the template and falls back on this
 * copy; `module:make` writes this copy, so both commands produce the same module.
 */
class ModuleTemplate
{
    /**
     * Opt-in layers, in the order they are written, with the layers each implies.
     *
     * @var array<string, list<string>>
     */
    public const array LAYERS = [
        'provider' => [],
        'routes' => ['provider'],
        'api' => ['routes'],
        'config' => ['provider'],
        'database' => [],
        'tests' => [],
    ];

    /**
     * Files of the template that carry the Vite build, left out by --no-assets.
     *
     * @var list<string>
     */
    public const array ASSET_FILES = ['package.json', 'vite.config.js', 'resources/assets/'];

    public function __construct(private readonly ModuleScaffolderService $scaffolder) {}

    /**
     * Directory of the bundled copy of Pollora/module-default.
     */
    public function defaultTemplatePath(): string
    {
        return dirname(__DIR__, 2).'/stubs/module-default';
    }

    /**
     * Placeholder replacements for a module.
     *
     * @return array<string, string>
     */
    public function replacements(string $name, string $namespace, string $description = '', string $author = ''): array
    {
        $studly = Str::studly($name);
        $moduleNamespace = trim($namespace, '\\').'\\'.$studly;

        return [
            '%module_name%' => $studly,
            '%module_slug%' => Str::kebab($studly),
            '%module_lower%' => strtolower($studly),
            '%module_namespace%' => $moduleNamespace,
            '%module_namespace_json%' => str_replace('\\', '\\\\', $moduleNamespace),
            '%module_description%' => $description !== '' ? $description : sprintf('The %s module', $studly),
            '%module_author%' => $author !== '' ? $author : 'Pollora',
        ];
    }

    /**
     * Write the bundled lean tree into a module directory.
     *
     * @param  array<string, string>  $replacements
     */
    public function writeDefault(string $modulePath, array $replacements, bool $withAssets = true): void
    {
        $this->scaffolder->copyDirectoryWithReplacements(
            $this->defaultTemplatePath(),
            $modulePath,
            $replacements,
            fn (object $item): bool => $withAssets || ! $this->isAssetFile($item->getRelativePathname()),
        );

        $this->scaffolder->ensureDirectoryExists($modulePath.'/resources/views/blocks');
    }

    /**
     * Whether a template file belongs to the Vite build.
     */
    public function isAssetFile(string $relativePath): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);

        foreach (self::ASSET_FILES as $assetFile) {
            if (str_ends_with($assetFile, '/') ? str_starts_with($relativePath, $assetFile) : $relativePath === $assetFile) {
                return true;
            }
        }

        return false;
    }

    /**
     * The layers to write for the requested ones, with what they imply, in order.
     *
     * @param  list<string>  $requested
     * @return list<string>
     */
    public function resolveLayers(array $requested): array
    {
        $resolved = [];
        $pending = $requested;

        while ($pending !== []) {
            $layer = array_shift($pending);

            if (! array_key_exists($layer, self::LAYERS) || in_array($layer, $resolved, true)) {
                continue;
            }

            $resolved[] = $layer;
            array_push($pending, ...self::LAYERS[$layer]);
        }

        return array_values(array_filter(array_keys(self::LAYERS), fn (string $layer): bool => in_array($layer, $resolved, true)));
    }

    /**
     * Add Laravel layers to a module: their files, the provider in module.json,
     * and the PSR-4 entries of the database and tests directories.
     *
     * @param  list<string>  $layers  Layers already resolved with resolveLayers()
     * @param  array<string, string>  $replacements
     */
    public function addLayers(string $modulePath, array $layers, array $replacements): void
    {
        $namespace = $replacements['%module_namespace%'];
        $providers = in_array('routes', $layers, true) ? [$namespace.'\\Providers\\RouteServiceProvider'] : [];
        $replacements['%module_providers%'] = implode(', ', array_map(
            fn (string $provider): string => '\\'.$provider.'::class',
            $providers,
        ));

        foreach ($layers as $layer) {
            $layerPath = dirname(__DIR__, 2).'/stubs/module-layers/'.$layer;

            if (is_dir($layerPath)) {
                $this->scaffolder->copyDirectoryWithReplacements($layerPath, $modulePath, $replacements);
            }
        }

        if (in_array('database', $layers, true)) {
            foreach (['migrations', 'seeders', 'factories'] as $directory) {
                $this->scaffolder->ensureDirectoryExists($modulePath.'/database/'.$directory);
            }
        }

        if (in_array('tests', $layers, true)) {
            $this->scaffolder->ensureDirectoryExists($modulePath.'/tests/Unit');
        }

        if (in_array('provider', $layers, true)) {
            $this->updateJson($modulePath.'/module.json', function (array $manifest) use ($namespace): array {
                $provider = $namespace.'\\Providers\\'.basename(str_replace('\\', '/', $namespace)).'ServiceProvider';
                $manifest['providers'] = array_values(array_unique([...($manifest['providers'] ?? []), $provider]));

                return $manifest;
            });
        }

        $this->updateJson($modulePath.'/composer.json', function (array $composer) use ($layers, $namespace): array {
            if (in_array('database', $layers, true)) {
                $composer['autoload']['psr-4'][$namespace.'\\Database\\Factories\\'] = 'database/factories/';
                $composer['autoload']['psr-4'][$namespace.'\\Database\\Seeders\\'] = 'database/seeders/';
            }

            if (in_array('tests', $layers, true)) {
                $composer['autoload-dev']['psr-4'][$namespace.'\\Tests\\'] = 'tests/';
            }

            return $composer;
        });
    }

    /**
     * Rewrite a JSON file through a callback, keeping its formatting style.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $update
     */
    private function updateJson(string $path, callable $update): void
    {
        if (! is_file($path)) {
            return;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            return;
        }

        $updated = $update($data);

        if ($updated !== $data) {
            file_put_contents($path, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        }
    }
}

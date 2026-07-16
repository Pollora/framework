<?php

declare(strict_types=1);

use Coduo\PHPHumanizer\StringHumanizer;
use Illuminate\Support\Str;
use Pollora\Modules\Domain\Enums\ModuleType;
use Pollora\Plugin\Application\Services\PluginRegistrar;
use Pollora\Support\RecursiveMenuIterator;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;

if (! function_exists('pollora_register')) {
    /**
     * Register a module with the Pollora framework.
     *
     * This is the entry point for themes and plugins to declare themselves
     * to the framework. It replaces manual service resolution and error
     * handling with a single, type-safe call.
     *
     * **Theme** — auto-detects name and path from the active WordPress stylesheet:
     *
     *     use Pollora\Modules\Domain\Enums\ModuleType;
     *     pollora_register(ModuleType::Theme);
     *
     * **Plugin** — requires the plugin slug and directory path:
     *
     *     use Pollora\Modules\Domain\Enums\ModuleType;
     *     pollora_register(ModuleType::Plugin, 'my-plugin', __DIR__);
     *
     * Registration triggers autoloading, discovery, configuration loading,
     * asset setup, and route loading for the module. Errors are silently
     * logged — a failed registration does not crash the application.
     *
     * @param  ModuleType  $type  The type of module to register
     * @param  string|null  $name  The module name/slug (required for plugins, ignored for themes)
     * @param  string|null  $path  The module root directory (required for plugins, ignored for themes)
     *
     * @see ThemeRegistrarInterface  Handles theme registration
     * @see PluginRegistrar  Handles plugin registration
     * @see ModuleType  Available module types
     */
    function pollora_register(ModuleType $type, ?string $name = null, ?string $path = null): void
    {
        if (! function_exists('app')) {
            return;
        }

        try {
            match ($type) {
                ModuleType::Theme => resolve(ThemeRegistrarInterface::class)->register(),
                ModuleType::Plugin => resolve(PluginRegistrar::class)->register($name, $path),
            };
        } catch (Throwable $throwable) {
            if (app()->bound('log')) {
                resolve('log')->error(sprintf('Pollora: failed to register %s%s', $type->value, $name ? sprintf(' [%s]', $name) : ''), [
                    'error' => $throwable->getMessage(),
                ]);
            }
        }
    }
}

if (! function_exists('mysqli_report')) {
    /**
     * Report MySQL errors.
     */
    function mysqli_report(): void
    {
        // silence is golden
    }
}

if (! function_exists('is_secured')) {
    /**
     * Determine if the application URL is served over HTTPS.
     *
     * @return bool True when the configured app URL uses the HTTPS scheme
     */
    function is_secured(): bool
    {
        return str_contains((string) config('app.url'), 'https://');
    }
}

if (! function_exists('menu')) {
    /**
     * Get a {@link RecursiveIteratorIterator} for a WordPress menu.
     *
     * @param  string  $name  name of the menu to get
     * @param  int  $depth  how far to recurse down the nodes
     * @param  int  $mode  flags to pass to the {@link RecursiveIteratorIterator}
     */
    function menu(string $name, $depth = -1, int $mode = RecursiveIteratorIterator::SELF_FIRST): RecursiveIteratorIterator
    {
        /** @var 0|1|2 $mode */
        $iterator = new RecursiveIteratorIterator(new RecursiveMenuIterator($name), $mode);
        $iterator->setMaxDepth($depth);

        return $iterator;
    }
}

if (! function_exists('humanize_class_name')) {
    /**
     * Humanize a class name to create a readable name.
     *
     * @param  string  $className  The class name to humanize
     * @return string The humanized class name
     */
    function humanize_class_name(string $className): string
    {
        // Get the class name without namespace
        $className = class_basename($className);

        // Convert from camelCase or PascalCase to words with spaces
        $humanized = StringHumanizer::humanize(
            Str::snake($className)
        );

        return $humanized;
    }
}

if (! function_exists('singularize')) {
    /**
     * Get the singular form of a word.
     *
     * @param  string  $word  The word to singularize
     * @return string The singular form
     */
    function singularize(string $word): string
    {
        return Str::singular($word);
    }
}

if (! function_exists('pluralize')) {
    /**
     * Get the plural form of a word.
     *
     * @param  string  $word  The word to pluralize
     * @return string The plural form
     */
    function pluralize(string $word): string
    {
        return Str::plural($word);
    }
}

<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Theme\Domain\Contracts\ThemeService;

/**
 * Facade for WordPress Theme functionality.
 *
 * Provides a fluent interface for managing WordPress theme features and settings
 * with improved type safety and modern PHP syntax.
 *
 * @method static ThemeService instance() Get the ThemeService instance
 * @method static void load(string $themeName) Load a theme by name
 * @method static string path(string $path) Get path relative to the active theme
 * @method static string|null active() Get the active theme name
 * @method static \Pollora\Theme\Domain\Models\ThemeMetadata|null theme() Get the theme metadata object
 * @method static string|null parent() Get the parent theme name
 * @method static array getParentThemes() Get the parent themes of the active theme
 * @method static array getAvailableThemes() Get the metadata of the available themes
 * @method static bool hasTheme(string $name) Check whether a theme exists
 * @method static \Pollora\Modules\Domain\Contracts\ModuleInterface|null getActiveTheme() Get the active theme module
 */
class Theme extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return ThemeService::class;
    }
}

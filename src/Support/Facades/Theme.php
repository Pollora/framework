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
 * @method static void addSupport(string|array $feature, mixed $args = null) Add theme support for a feature
 * @method static void removeSupport(string $feature) Remove theme support for a feature
 * @method static bool supports(string $feature) Check if theme supports a feature
 * @method static void loadTextDomain(string $domain, string $path = '') Load theme text domain
 * @method static void load(string $themeName) Load a theme by name
 * @method static string path(string $path) Get path relative to the active theme
 * @method static string|bool active() Get the active theme name
 * @method static \Pollora\Theme\Domain\Models\ThemeMetadata|null theme() Get the theme metadata object
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

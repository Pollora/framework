<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for WordPress Theme functionality.
 *
 * Provides a fluent interface for managing WordPress theme features and settings
 * with improved type safety and modern PHP syntax.
 *
 * @method static void addSupport(string|array $feature, mixed $args = null) Add theme support for a feature
 * @method static void removeSupport(string $feature) Remove theme support for a feature
 * @method static bool supports(string $feature) Check if theme supports a feature
 * @method static void loadTextDomain(string $domain, string $path = '') Load theme text domain
 *
 * @see \Pollora\Theme\Theme
 */
class Theme extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'theme';
    }
}

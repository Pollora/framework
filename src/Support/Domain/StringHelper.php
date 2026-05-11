<?php

declare(strict_types=1);

namespace Pollora\Support\Domain;

/**
 * Framework-agnostic string transformation utilities for the Domain layer.
 *
 * Provides common string case conversions without depending on
 * Illuminate\Support\Str, keeping Domain classes decoupled from Laravel.
 */
final class StringHelper
{
    /**
     * Convert a string to StudlyCase (PascalCase).
     *
     * @example StringHelper::studly('foo-bar_baz') // 'FooBarBaz'
     */
    public static function studly(string $value): string
    {
        return str_replace(['-', '_', ' '], '', ucwords($value, '-_ '));
    }

    /**
     * Convert a string to kebab-case.
     *
     * @example StringHelper::kebab('FooBarBaz') // 'foo-bar-baz'
     */
    public static function kebab(string $value): string
    {
        return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $value));
    }

    /**
     * Convert a string to snake_case.
     *
     * @example StringHelper::snake('FooBarBaz') // 'foo_bar_baz'
     */
    public static function snake(string $value): string
    {
        return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $value));
    }

    /**
     * Convert a string to Title Case with spaces.
     *
     * @example StringHelper::headline('foo_bar-baz') // 'Foo Bar Baz'
     */
    public static function headline(string $value): string
    {
        // Split on camelCase boundaries, underscores, hyphens, and spaces
        $words = (string) preg_replace('/([a-z])([A-Z])/', '$1 $2', $value);
        $words = str_replace(['-', '_'], ' ', $words);

        return ucwords(strtolower($words));
    }
}

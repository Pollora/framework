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

    /**
     * Get the singular form of an English word.
     *
     * Handles common English inflection rules. For edge cases,
     * classes can override the method that calls this.
     *
     * @example StringHelper::singular('Categories') // 'Category'
     * @example StringHelper::singular('Posts') // 'Post'
     * @example StringHelper::singular('Addresses') // 'Address'
     */
    public static function singular(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $lower = strtolower($value);

        // Uncountable words
        if (in_array($lower, ['media', 'news', 'series', 'species', 'information', 'data'], true)) {
            return $value;
        }

        // Irregular plurals
        $irregulars = [
            'people' => 'person', 'men' => 'man', 'women' => 'woman',
            'children' => 'child', 'mice' => 'mouse', 'geese' => 'goose',
            'teeth' => 'tooth', 'feet' => 'foot', 'oxen' => 'ox',
        ];

        if (isset($irregulars[$lower])) {
            return self::matchCase($value, $irregulars[$lower]);
        }

        // Rule-based singularization (order matters)
        $rules = [
            '/(quiz)zes$/i' => '$1',
            '/(matr|vert|append)ices$/i' => '$1ix',
            '/(alias|status)es$/i' => '$1',
            '/(x|ch|ss|sh)es$/i' => '$1',
            '/ies$/i' => 'y',
            '/ves$/i' => 'fe',
            '/([^s])s$/i' => '$1',
        ];

        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $value)) {
                return (string) preg_replace($pattern, $replacement, $value);
            }
        }

        return $value;
    }

    /**
     * Get the plural form of an English word.
     *
     * Handles common English inflection rules.
     *
     * @example StringHelper::plural('Category') // 'Categories'
     * @example StringHelper::plural('Post') // 'Posts'
     * @example StringHelper::plural('Address') // 'Addresses'
     */
    public static function plural(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $lower = strtolower($value);

        // Uncountable words
        if (in_array($lower, ['media', 'news', 'series', 'species', 'information', 'data'], true)) {
            return $value;
        }

        // Irregular plurals
        $irregulars = [
            'person' => 'people', 'man' => 'men', 'woman' => 'women',
            'child' => 'children', 'mouse' => 'mice', 'goose' => 'geese',
            'tooth' => 'teeth', 'foot' => 'feet', 'ox' => 'oxen',
        ];

        if (isset($irregulars[$lower])) {
            return self::matchCase($value, $irregulars[$lower]);
        }

        // Rule-based pluralization (order matters)
        $rules = [
            '/(quiz)$/i' => '$1zes',
            '/(matr|vert|append)ix$/i' => '$1ices',
            '/(alias|status)$/i' => '$1es',
            '/(x|ch|ss|sh)$/i' => '$1es',
            '/([^aeiouy])y$/i' => '$1ies',
            '/(fe?)$/i' => 'ves',
            '/s$/i' => 'ses',
            '/$/' => 's',
        ];

        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $value)) {
                return (string) preg_replace($pattern, $replacement, $value);
            }
        }

        return $value.'s';
    }

    /**
     * Match the case of the original word to the replacement.
     */
    private static function matchCase(string $original, string $replacement): string
    {
        if (ctype_upper($original[0])) {
            return ucfirst($replacement);
        }

        return $replacement;
    }
}

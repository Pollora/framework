<?php

declare(strict_types=1);

use Coduo\PHPHumanizer\StringHumanizer;
use Illuminate\Support\Str;

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

<?php

declare(strict_types=1);

use Coduo\PHPHumanizer\StringHumanizer;
use Illuminate\Support\Str;
use Pollora\Support\RecursiveMenuIterator;

if (! function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = []): bool
    {
        $result = app('wp.mail')->send($to, $subject, $message, $headers, $attachments);

        return $result !== null;
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
        $humanized = \Coduo\PHPHumanizer\StringHumanizer::humanize(
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

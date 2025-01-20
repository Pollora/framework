<?php

declare(strict_types=1);

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

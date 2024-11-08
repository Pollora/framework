<?php
use Pollora\Support\RecursiveMenuIterator;

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

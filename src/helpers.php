<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Pollen\Models\Meta;
use Pollen\Models\Post;
use Pollen\Support\RecursiveMenuIterator;

if (! function_exists('__')) {
    /**
     * Tries to get a translation from both Laravel and WordPress.
     *
     * @param $key key of the translation
     * @param  array|string  $replace replacements for laravel or domain for wordpress
     * @param  string  $locale locale for laravel, not used for wordpress
     * @return string
     */
    function __($key, $replace = [], $locale = null)
    {
        if (! $locale) {
            $locale = get_locale();
        }
        if (is_array($replace) && Lang::has($key, $locale)) {
            try {
                return trans($key, $replace, $locale);
            } catch (\Exception $e) {
                // failed to get translation from Laravel
                if ((! empty($replace) && ! is_string($replace)) || ! empty($locale)) {
                    // this doesn't look like something we can pass to WordPress, lets
                    // rethrow the exception
                    throw $e;
                }
            }
        }

        $key = str_replace('wordpress.', '', $key);

        return translate($key, empty($replace) ? 'default' : $replace);
    }
}

if (! function_exists('query')) {
    /**
     * Get the main query or convert a {@link WP_Query} to a {@link \Pollen\Proxy\Query} proxy instance.
     *
     *
     * @return \Pollen\Proxy\Query
     */
    function query(WP_Query $query = null)
    {
        return ($query === null) ? app('query') : Query::instance($query);
    }
}

if (! function_exists('post')) {
    /**
     * Get the current post in The Loop, or convert a {@link \WP_Post} instance to a Pollen
     * post.
     *
     *
     * @return Post|null
     */
    function post(WP_Post $post = null)
    {
        return ($post === null) ? app('loop') : Post::find($post->ID);
    }
}

if (! function_exists('meta')) {
    /**
     * Grab a meta item from the database for the current page.
     *
     * @param  string|null  $name name of the field to get (or null for all)
     * @return mixed
     */
    function meta($name = null)
    {
        return Meta::get($name);
    }
}

if (! function_exists('field')) {
    /**
     * Grab an ACF field from the database for the current page.
     *
     * @see Meta::acf()
     *
     * @param  string|null  $name name of the field to get (or null for all)
     * @return mixed
     */
    function field($name = null)
    {
        return Meta::acf($name);
    }
}

if (! function_exists('menu')) {
    /**
     * Get a {@link RecursiveIteratorIterator} for a WordPress menu.
     *
     * @param  string  $name name of the menu to get
     * @param  int  $depth how far to recurse down the nodes
     * @param  int  $mode flags to pass to the {@link RecursiveIteratorIterator}
     * @return RecursiveIteratorIterator
     */
    function menu($name, $depth = -1, $mode = RecursiveIteratorIterator::SELF_FIRST)
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveMenuIterator($name), $mode);
        $iterator->setMaxDepth($depth);

        return $iterator;
    }
}

if (! function_exists('is_secured')) {
    function is_secured()
    {
        return str_contains(config('app.url'), 'https://');
    }
}

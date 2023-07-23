<?php

declare(strict_types=1);

/**
 * Class PostTypeServiceProvider
 * @package Pollen\Providers
 */
namespace Pollen\Providers;

use Illuminate\Support\ServiceProvider;
use Pollen\Support\Facades\Action;
use Pollen\Services\Translater;

/**
 * Class PostTypeServiceProvider
 *
 * A service provider for registering custom post types.
 */
class PostTypeServiceProvider extends ServiceProvider
{
    public function register()
    {
            Action::add('init', [$this, 'registerPostTypes'], 1);
    }

    /**
     * Register all the site's custom post types
     *
     * @return void
     */
    public function registerPostTypes()
    {
        // Get the post types from the config.
        $postTypes = config('post-types');

        $translater = new Translater($postTypes, 'post-types');
        $postTypes = $translater->translate([
            '*.label',
            '*.labels.*',
            '*.names.singular',
            '*.names.plural',
        ]);

        // Iterate over each post type.
        collect($postTypes)->each(function ($item, $key) {

            // Check if names are set, if not keep it as an empty array
            $names = $item['names'] ?? [];

            // Unset names from item
            unset($item['names']);

            // Register the extended post type.
            register_extended_post_type($key, $item, $names);
        });
    }
}

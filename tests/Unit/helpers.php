<?php

declare(strict_types=1);

class WP
{
    public static $wpFunctions;
}

/**
 * Setup WordPress mock functions for tests
 */
function setupWordPressMocks()
{
    // Initialize WP::$wpFunctions if not already set
    if (!isset(WP::$wpFunctions) || !WP::$wpFunctions) {
        WP::$wpFunctions = Mockery::mock('stdClass');
    }

    // Mock WordPress hook functions with specific handlers for common filters
    WP::$wpFunctions->shouldReceive('add_filter')
        ->withArgs(function($hook, $callback, $priority = 10, $accepted_args = 1) {
            // Allow any template_include filter to be registered
            if ($hook === 'template_include') {
                return true;
            }

            // Allow template_redirect action to be registered
            if ($hook === 'template_redirect') {
                return true;
            }

            // Default behavior for other hooks
            return true;
        })
        ->andReturn(true)
        ->byDefault();

    WP::$wpFunctions->shouldReceive('apply_filters')
        ->withAnyArgs()
        ->andReturnUsing(function($tag, $value) {
            return $value;
        })
        ->byDefault();

    // Default WordPress conditional functions behavior
    WP::$wpFunctions->shouldReceive('is_page')
        ->byDefault()
        ->andReturn(true);

    WP::$wpFunctions->shouldReceive('is_singular')
        ->byDefault()
        ->andReturn(true);

    WP::$wpFunctions->shouldReceive('is_archive')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_404')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_search')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_category')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_tag')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_tax')
        ->byDefault()
        ->andReturn(false);

    // Mock scheduling functions
    WP::$wpFunctions->shouldReceive('wp_next_scheduled')
        ->withAnyArgs()
        ->andReturn(false)
        ->byDefault();

    WP::$wpFunctions->shouldReceive('wp_schedule_event')
        ->withAnyArgs()
        ->andReturn(true)
        ->byDefault();

    // Mock WordPress template functions
    WP::$wpFunctions->shouldReceive('get_page_template_slug')
        ->withAnyArgs()
        ->andReturn('template-custom.php')
        ->byDefault();

    WP::$wpFunctions->shouldReceive('get_queried_object')
        ->withAnyArgs()
        ->andReturnUsing(function() {
            $obj = new stdClass();
            $obj->post_type = "page";
            $obj->post_name = "test-page";
            $obj->ID = 123;
            return $obj;
        })
        ->byDefault();

    WP::$wpFunctions->shouldReceive('get_post')
        ->withAnyArgs()
        ->andReturnUsing(function() {
            $post = new stdClass();
            $post->post_name = "parent-page";
            $post->post_parent = 0;
            return $post;
        })
        ->byDefault();

    WP::$wpFunctions->shouldReceive('get_query_var')
        ->withAnyArgs()
        ->andReturnUsing(function($var) {
            return $var === "post_type" ? "page" : "";
        })
        ->byDefault();
}

/**
 * Convenience function to set mock WordPress condition values
 */
function setWordPressConditions(array $conditions = [])
{
    // Make sure WP::$wpFunctions is initialized
    if (!isset(WP::$wpFunctions) || !WP::$wpFunctions) {
        setupWordPressMocks();
    }

    // Set each condition value
    foreach ($conditions as $condition => $value) {
        if (method_exists(WP::$wpFunctions, 'shouldReceive')) {
            WP::$wpFunctions->shouldReceive($condition)
                ->andReturn($value);
        }
    }
}

/**
 * Helper functions for tests
 */
if (! function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param  string|null  $abstract
     * @return mixed|\Illuminate\Contracts\Foundation\Application
     */
    function app($abstract = null, array $parameters = [])
    {
        $app = \Illuminate\Container\Container::getInstance();

        if (is_null($abstract)) {
            return $app;
        }

        return $app->make($abstract, $parameters);
    }
}

if (! function_exists('app_path')) {
    /**
     * Get the path to the application folder.
     *
     * @param  string  $path
     * @return string
     */
    function app_path($path = '')
    {
        return __DIR__.'/../../app/'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}

if (! function_exists('config_path')) {
    /**
     * Get the path to the config folder.
     *
     * @param  string  $path
     * @return string
     */
    function config_path($path = '')
    {
        return __DIR__.'/../../config/'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}

if (! function_exists('base_path')) {
    /**
     * Get the path to the base of the install.
     *
     * @param  string  $path
     * @return string
     */
    function base_path($path = '')
    {
        return __DIR__.'/../..'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}

/**
 * WordPress mock functions
 */
if (! function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        return WP::$wpFunctions->add_filter($tag, $function_to_add, $priority, $accepted_args);
    }
}

if (! function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        return WP::$wpFunctions->add_filter($tag, $function_to_add, $priority, $accepted_args);
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args)
    {
        return WP::$wpFunctions->apply_filters($tag, $value, ...$args);
    }
}

if (! function_exists('get_queried_object')) {
    function get_queried_object()
    {
        return WP::$wpFunctions->get_queried_object();
    }
}

if (! function_exists('get_page_template_slug')) {
    function get_page_template_slug($page_id)
    {
        return WP::$wpFunctions->get_page_template_slug($page_id);
    }
}

if (! function_exists('get_post')) {
    function get_post($post_id)
    {
        return WP::$wpFunctions->get_post($post_id);
    }
}

if (! function_exists('get_query_var')) {
    function get_query_var($var)
    {
        return WP::$wpFunctions->get_query_var($var);
    }
}

/**
 * WordPress scheduling functions
 */
if (! function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = [])
    {
        return WP::$wpFunctions->wp_next_scheduled($hook, $args);
    }
}

if (! function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = [])
    {
        return WP::$wpFunctions->wp_schedule_event($timestamp, $recurrence, $hook, $args);
    }
}

/**
 * WordPress conditional functions
 */
if (! function_exists('is_page')) {
    function is_page() {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_page() : true;
    }
}

if (! function_exists('is_singular')) {
    function is_singular() {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_singular() : true;
    }
}

if (! function_exists('is_archive')) {
    function is_archive() {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_archive() : false;
    }
}

if (! function_exists('is_404')) {
    function is_404() {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_404() : false;
    }
}

if (! function_exists('is_search')) {
    function is_search() {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_search() : false;
    }
}

if (! function_exists('is_category')) {
    function is_category() {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_category() : false;
    }
}

if (! function_exists('is_tag')) {
    function is_tag() {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_tag() : false;
    }
}

if (! function_exists('is_tax')) {
    function is_tax() {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_tax() : false;
    }
}

if (! function_exists('wp_is_block_theme')) {
    function wp_is_block_theme() {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->wp_is_block_theme() : false;
    }
}

if (! function_exists('__return_true')) {
    function __return_true() { return true; }
}

if (! function_exists('__return_false')) {
    function __return_false() { return false; }
}

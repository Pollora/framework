<?php

declare(strict_types=1);

use Illuminate\Container\Container;

// Define WordPress constants for tests
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

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
    if (! isset(WP::$wpFunctions) || ! WP::$wpFunctions) {
        WP::$wpFunctions = Mockery::mock('stdClass');
    }

    // Mock WordPress hook functions with specific handlers for common filters
    WP::$wpFunctions->shouldReceive('add_filter')
        ->withArgs(function ($hook, $callback, $priority = 10, $accepted_args = 1) {
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
        ->andReturnUsing(function ($tag, $value) {
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

    WP::$wpFunctions->shouldReceive('register_rest_route')
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

    WP::$wpFunctions->shouldReceive('is_attachment')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_single')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_home')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_front_page')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_author')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_date')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_year')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_month')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_day')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_time')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('get_post_mime_type')
        ->byDefault()
        ->andReturn('text/plain');

    WP::$wpFunctions->shouldReceive('get_the_category')
        ->byDefault()
        ->andReturn([]);

    WP::$wpFunctions->shouldReceive('get_term')
        ->byDefault()
        ->andReturn(null);

    WP::$wpFunctions->shouldReceive('get_userdata')
        ->byDefault()
        ->andReturn(null);

    WP::$wpFunctions->shouldReceive('get_queried_object_id')
        ->byDefault()
        ->andReturn(0);

    WP::$wpFunctions->shouldReceive('get_template')
        ->byDefault()
        ->andReturn('theme');

    WP::$wpFunctions->shouldReceive('get_stylesheet')
        ->byDefault()
        ->andReturn('theme');

    WP::$wpFunctions->shouldReceive('is_admin')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_main_query')
        ->byDefault()
        ->andReturn(true);

    // Mock WooCommerce functions
    WP::$wpFunctions->shouldReceive('is_shop')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_product')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_cart')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_checkout')
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
        ->andReturnUsing(function () {
            $obj = new stdClass;
            $obj->post_type = 'page';
            $obj->post_name = 'test-page';
            $obj->ID = 123;

            return $obj;
        })
        ->byDefault();

    WP::$wpFunctions->shouldReceive('get_post')
        ->withAnyArgs()
        ->andReturnUsing(function () {
            $post = new stdClass;
            $post->post_name = 'parent-page';
            $post->post_parent = 0;

            return $post;
        })
        ->byDefault();

    WP::$wpFunctions->shouldReceive('get_query_var')
        ->withAnyArgs()
        ->andReturnUsing(function ($var) {
            return $var === 'post_type' ? 'page' : '';
        })
        ->byDefault();
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
        $app = Container::getInstance();

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

if (! function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false)
    {
        return WP::$wpFunctions->get_post_meta($post_id, $key, $single);
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
    function is_page()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_page() : true;
    }
}

if (! function_exists('is_singular')) {
    function is_singular()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_singular() : true;
    }
}

if (! function_exists('is_archive')) {
    function is_archive()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_archive() : false;
    }
}

if (! function_exists('is_404')) {
    function is_404()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_404() : false;
    }
}

if (! function_exists('is_search')) {
    function is_search()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_search() : false;
    }
}

if (! function_exists('is_category')) {
    function is_category()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_category() : false;
    }
}

if (! function_exists('is_tag')) {
    function is_tag()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_tag() : false;
    }
}

if (! function_exists('is_tax')) {
    function is_tax()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_tax() : false;
    }
}

if (! function_exists('wp_is_block_theme')) {
    function wp_is_block_theme()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->wp_is_block_theme() : false;
    }
}

if (! function_exists('is_attachment')) {
    function is_attachment()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_attachment() : false;
    }
}

if (! function_exists('is_single')) {
    function is_single()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_single() : false;
    }
}

if (! function_exists('is_home')) {
    function is_home()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_home() : false;
    }
}

if (! function_exists('is_main_query')) {
    function is_main_query()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_main_query() : true;
    }
}

/**
 * WooCommerce functions
 */
if (! function_exists('is_shop')) {
    function is_shop()
    {
        return isset($GLOBALS['is_shop']) ? $GLOBALS['is_shop'] : false;
    }
}

if (! function_exists('is_product')) {
    function is_product()
    {
        return isset($GLOBALS['is_product']) ? $GLOBALS['is_product'] : false;
    }
}

if (! function_exists('is_cart')) {
    function is_cart()
    {
        return isset($GLOBALS['is_cart']) ? $GLOBALS['is_cart'] : false;
    }
}

if (! function_exists('is_checkout')) {
    function is_checkout()
    {
        return isset($GLOBALS['is_checkout']) ? $GLOBALS['is_checkout'] : false;
    }
}

if (! function_exists('is_product_category')) {
    function is_product_category()
    {
        return isset($GLOBALS['is_product_category']) ? $GLOBALS['is_product_category'] : false;
    }
}

if (! function_exists('is_product_tag')) {
    function is_product_tag()
    {
        return isset($GLOBALS['is_product_tag']) ? $GLOBALS['is_product_tag'] : false;
    }
}

if (! function_exists('is_product_taxonomy')) {
    function is_product_taxonomy()
    {
        return isset($GLOBALS['is_product_taxonomy']) ? $GLOBALS['is_product_taxonomy'] : false;
    }
}

if (! function_exists('wc_get_product')) {
    function wc_get_product($product = false)
    {
        return isset($GLOBALS['wc_get_product']) ? $GLOBALS['wc_get_product'] : false;
    }
}

if (! function_exists('is_home')) {
    function is_home()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_home() : false;
    }
}

if (! function_exists('is_front_page')) {
    function is_front_page()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_front_page() : false;
    }
}

if (! function_exists('is_author')) {
    function is_author()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_author() : false;
    }
}

if (! function_exists('is_date')) {
    function is_date()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_date() : false;
    }
}

if (! function_exists('is_year')) {
    function is_year()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_year() : false;
    }
}

if (! function_exists('is_month')) {
    function is_month()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_month() : false;
    }
}

if (! function_exists('is_day')) {
    function is_day()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_day() : false;
    }
}

if (! function_exists('is_time')) {
    function is_time()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_time() : false;
    }
}

if (! function_exists('get_post_mime_type')) {
    function get_post_mime_type($post_id = null)
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_post_mime_type($post_id) : 'text/plain';
    }
}

if (! function_exists('get_the_category')) {
    function get_the_category($post_id = null)
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_the_category($post_id) : [];
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}

if (! function_exists('get_term')) {
    function get_term($term_id, $taxonomy = '', $output = OBJECT, $filter = 'raw')
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_term($term_id, $taxonomy, $output, $filter) : null;
    }
}

if (! function_exists('get_userdata')) {
    function get_userdata($user_id)
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_userdata($user_id) : null;
    }
}

if (! function_exists('get_queried_object_id')) {
    function get_queried_object_id()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_queried_object_id() : 0;
    }
}

if (! function_exists('get_template')) {
    function get_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_template() : 'theme';
    }
}

if (! function_exists('get_stylesheet')) {
    function get_stylesheet()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_stylesheet() : 'theme';
    }
}

if (! function_exists('is_admin')) {
    function is_admin()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_admin() : false;
    }
}

if (! function_exists('is_main_query')) {
    function is_main_query()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_main_query() : true;
    }
}

if (! function_exists('view')) {
    function view($view = null, $data = [], $mergeData = [])
    {
        // Mock function for Laravel view helper
        return app('view');
    }
}

// Mock WooCommerce functions for plugin condition tests
if (! function_exists('is_shop')) {
    function is_shop()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_shop() : false;
    }
}

if (! function_exists('is_product')) {
    function is_product()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_product() : false;
    }
}

if (! function_exists('is_cart')) {
    function is_cart()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_cart() : false;
    }
}

if (! function_exists('is_checkout')) {
    function is_checkout()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_checkout() : false;
    }
}
if (! function_exists('get_file_data')) {
    function get_file_data($file, $headers)
    {
        return ['title' => 'Title', 'slug' => 'slug-demo', 'description' => 'Desc'];
    }
}

if (! function_exists('__return_true')) {
    function __return_true()
    {
        return true;
    }
}

if (! function_exists('__return_false')) {
    function __return_false()
    {
        return false;
    }
}

/**
 * Helper function for route condition testing
 */
if (! function_exists('route_condition_test')) {
    function route_condition_test($param = null)
    {
        return false; // Default implementation, will be mocked in tests
    }
}

/**
 * Set a specific WordPress function mock
 */
function setWordPressFunction(string $functionName, callable $callback): void
{
    if (!isset(WP::$wpFunctions) || !WP::$wpFunctions) {
        setupWordPressMocks();
    }
    
    WP::$wpFunctions->shouldReceive($functionName)
        ->withAnyArgs()
        ->andReturnUsing($callback)
        ->byDefault();
}

/**
 * Set WordPress conditions for testing
 */
function setWordPressConditions(array $conditions): void
{
    foreach ($conditions as $condition => $value) {
        setWordPressFunction($condition, fn() => $value);
    }
}

/**
 * Reset WordPress mocks
 */
function resetWordPressMocks(): void
{
    if (isset(WP::$wpFunctions)) {
        Mockery::close();
        WP::$wpFunctions = null;
    }
}

if (! function_exists('translate_with_gettext_context')) {
    function translate_with_gettext_context($text, $context, $domain = null)
    {
        return $text;
    }
}

// MockActionFacade for WordPressAjaxActionRegistrarTest
if (! class_exists('MockActionFacade')) {
    class MockActionFacade
    {
        public array $calls = [];

        public function add($hook, $callback): void
        {
            $GLOBALS['pollora_action_calls'][] = [$hook, $callback];
        }
    }
}

// Generic TestContainer for service locator pattern in tests
if (! class_exists('TestContainer')) {
    class TestContainer
    {
        private array $services;

        public function __construct(array $services = [])
        {
            $this->services = $services;
        }

        public function get(string $serviceClass): ?object
        {
            return $this->services[$serviceClass] ?? null;
        }

        // Ajout pour compatibilité avec les tests d'attributs
        public function make(string $serviceClass): ?object
        {
            return $this->get($serviceClass);
        }

        public function resolve(string $serviceClass): ?object
        {
            return $this->get($serviceClass);
        }

        public function has(string $serviceClass): bool
        {
            return isset($this->services[$serviceClass]);
        }

        public function instance(string $abstract, $instance): void
        {
            $this->services[$abstract] = $instance;
        }
    }
}

// Mock WP_Error class for WordPress tests
if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct()
        {
            // Mock implementation
        }
    }
}


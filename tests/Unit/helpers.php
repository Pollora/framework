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

    // Mock WooCommerce specific functions
    WP::$wpFunctions->shouldReceive('locate_template')
        ->withAnyArgs()
        ->andReturn('')
        ->byDefault();

    WP::$wpFunctions->shouldReceive('add_theme_support')
        ->withAnyArgs()
        ->andReturn(true)
        ->byDefault();

    WP::$wpFunctions->shouldReceive('is_child_theme')
        ->withAnyArgs()
        ->andReturn(false)
        ->byDefault();

    WP::$wpFunctions->shouldReceive('get_stylesheet_directory')
        ->withAnyArgs()
        ->andReturn('/theme/child')
        ->byDefault();

    WP::$wpFunctions->shouldReceive('wp_doing_ajax')
        ->withAnyArgs()
        ->andReturn(false)
        ->byDefault();

    WP::$wpFunctions->shouldReceive('doing_action')
        ->withAnyArgs()
        ->andReturn(false)
        ->byDefault();

    // WordPress admin functions
    WP::$wpFunctions->shouldReceive('is_admin')
        ->withAnyArgs()
        ->andReturn(false)
        ->byDefault();

    WP::$wpFunctions->shouldReceive('get_current_screen')
        ->withAnyArgs()
        ->andReturn(new WP_Screen())
        ->byDefault();

    WP::$wpFunctions->shouldReceive('get_template_directory')
        ->withAnyArgs()
        ->andReturn('/theme')
        ->byDefault();

    WP::$wpFunctions->shouldReceive('WC')
        ->withAnyArgs()
        ->andReturn(null)
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

    WP::$wpFunctions->shouldReceive('is_front_page')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_home')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_single')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_author')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_date')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_page_template')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_attachment')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_embed')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_privacy_policy')
        ->byDefault()
        ->andReturn(false);

    WP::$wpFunctions->shouldReceive('is_post_type_archive')
        ->byDefault()
        ->andReturn(false);

    // Mock template functions to return empty by default
    WP::$wpFunctions->shouldReceive('get_embed_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_404_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_search_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_front_page_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_home_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_privacy_policy_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_post_type_archive_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_taxonomy_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_attachment_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_single_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_page_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_singular_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_category_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_tag_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_author_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_date_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_archive_template')
        ->byDefault()
        ->andReturn('');

    WP::$wpFunctions->shouldReceive('get_index_template')
        ->byDefault()
        ->andReturn('');

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
 * Convenience function to set mock WordPress condition values
 */
function setWordPressConditions(array $conditions = [])
{
    // Make sure WP::$wpFunctions is initialized
    if (! isset(WP::$wpFunctions) || ! WP::$wpFunctions) {
        setupWordPressMocks();
    }

    // Set each condition value
    foreach ($conditions as $condition => $value) {
        if (method_exists(WP::$wpFunctions, 'shouldReceive')) {
            WP::$wpFunctions->shouldReceive($condition)
                ->withAnyArgs()
                ->andReturn($value)
                ->byDefault();
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
    if (! isset(WP::$wpFunctions) || ! WP::$wpFunctions) {
        setupWordPressMocks();
    }

    WP::$wpFunctions->shouldReceive($functionName)
        ->withAnyArgs()
        ->andReturnUsing($callback)
        ->byDefault();
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

if (! function_exists('abort')) {
    function abort($code, $message = '')
    {
        throw new \Symfony\Component\HttpKernel\Exception\HttpException($code, $message);
    }
}

/**
 * WooCommerce mock functions
 */
if (! function_exists('locate_template')) {
    function locate_template($templates, $load = false, $require_once = true)
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->locate_template($templates, $load, $require_once) : '';
    }
}

if (! function_exists('add_theme_support')) {
    function add_theme_support($feature, $options = null)
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->add_theme_support($feature, $options) : true;
    }
}

if (! function_exists('is_child_theme')) {
    function is_child_theme()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_child_theme() : false;
    }
}

if (! function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_stylesheet_directory() : '/theme/child';
    }
}

if (! function_exists('wp_doing_ajax')) {
    function wp_doing_ajax()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->wp_doing_ajax() : false;
    }
}

if (! function_exists('doing_action')) {
    function doing_action($action = null)
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->doing_action($action) : false;
    }
}

if (! function_exists('WC')) {
    function WC()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->WC() : null;
    }
}

if (! function_exists('is_admin')) {
    function is_admin()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_admin() : false;
    }
}

if (! function_exists('get_current_screen')) {
    function get_current_screen()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_current_screen() : new WP_Screen();
    }
}

if (! function_exists('get_template_directory')) {
    function get_template_directory()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_template_directory() : '/theme';
    }
}

// Special WordPress request functions
if (! function_exists('wp_using_themes')) {
    function wp_using_themes()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->wp_using_themes() : true;
    }
}

if (! function_exists('is_robots')) {
    function is_robots()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_robots() : false;
    }
}

if (! function_exists('is_favicon')) {
    function is_favicon()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_favicon() : false;
    }
}

if (! function_exists('is_feed')) {
    function is_feed()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_feed() : false;
    }
}

if (! function_exists('is_trackback')) {
    function is_trackback()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_trackback() : false;
    }
}

if (! function_exists('do_feed')) {
    function do_feed()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->do_feed() : true;
    }
}

if (! function_exists('is_privacy_policy')) {
    function is_privacy_policy()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_privacy_policy() : false;
    }
}

if (! function_exists('is_post_type_archive')) {
    function is_post_type_archive()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_post_type_archive() : false;
    }
}

if (! function_exists('is_embed')) {
    function is_embed()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_embed() : false;
    }
}

if (! function_exists('is_front_page')) {
    function is_front_page()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_front_page() : false;
    }
}

if (! function_exists('is_home')) {
    function is_home()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_home() : false;
    }
}

if (! function_exists('is_single')) {
    function is_single()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_single() : false;
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

if (! function_exists('is_page_template')) {
    function is_page_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_page_template() : false;
    }
}

if (! function_exists('is_attachment')) {
    function is_attachment()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->is_attachment() : false;
    }
}

// Template hierarchy functions
if (! function_exists('get_embed_template')) {
    function get_embed_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_embed_template() : '';
    }
}

if (! function_exists('get_404_template')) {
    function get_404_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_404_template() : '';
    }
}

if (! function_exists('get_search_template')) {
    function get_search_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_search_template() : '';
    }
}

if (! function_exists('get_front_page_template')) {
    function get_front_page_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_front_page_template() : '';
    }
}

if (! function_exists('get_home_template')) {
    function get_home_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_home_template() : '';
    }
}

if (! function_exists('get_privacy_policy_template')) {
    function get_privacy_policy_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_privacy_policy_template() : '';
    }
}

if (! function_exists('get_post_type_archive_template')) {
    function get_post_type_archive_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_post_type_archive_template() : '';
    }
}

if (! function_exists('get_taxonomy_template')) {
    function get_taxonomy_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_taxonomy_template() : '';
    }
}

if (! function_exists('get_attachment_template')) {
    function get_attachment_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_attachment_template() : '';
    }
}

if (! function_exists('get_single_template')) {
    function get_single_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_single_template() : '';
    }
}

if (! function_exists('get_page_template')) {
    function get_page_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_page_template() : '';
    }
}

if (! function_exists('get_singular_template')) {
    function get_singular_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_singular_template() : '';
    }
}

if (! function_exists('get_category_template')) {
    function get_category_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_category_template() : '';
    }
}

if (! function_exists('get_tag_template')) {
    function get_tag_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_tag_template() : '';
    }
}

if (! function_exists('get_author_template')) {
    function get_author_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_author_template() : '';
    }
}

if (! function_exists('get_date_template')) {
    function get_date_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_date_template() : '';
    }
}

if (! function_exists('get_archive_template')) {
    function get_archive_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_archive_template() : '';
    }
}

if (! function_exists('get_index_template')) {
    function get_index_template()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_index_template() : '';
    }
}

// WordPress theme functions
if (! function_exists('get_template_directory')) {
    function get_template_directory()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_template_directory() : '/theme';
    }
}

if (! function_exists('get_theme_file_path')) {
    function get_theme_file_path()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_theme_file_path() : '/theme';
    }
}

if (! function_exists('get_body_class')) {
    function get_body_class()
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_body_class() : ['page'];
    }
}

if (! function_exists('current_theme_supports')) {
    function current_theme_supports($feature)
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->current_theme_supports($feature) : false;
    }
}

if (! function_exists('remove_filter')) {
    function remove_filter($tag, $function_to_remove, $priority = 10)
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->remove_filter($tag, $function_to_remove, $priority) : true;
    }
}

// WordPress cache functions
if (! function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '')
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->wp_cache_get($key, $group) : false;
    }
}

if (! function_exists('wp_cache_add')) {
    function wp_cache_add($key, $data, $group = '', $expire = 0)
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->wp_cache_add($key, $data, $group, $expire) : true;
    }
}

// WordPress text functions
if (! function_exists('translate')) {
    function translate($text, $domain = 'default')
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->translate($text, $domain) : $text;
    }
}

if (! function_exists('_cleanup_header_comment')) {
    function _cleanup_header_comment($str)
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->_cleanup_header_comment($str) : trim($str);
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->sanitize_key($key) : strtolower(trim($key));
    }
}

if (! function_exists('get_file_data')) {
    function get_file_data($file, $default_headers = [])
    {
        return isset(WP::$wpFunctions) ? WP::$wpFunctions->get_file_data($file, $default_headers) : [
            'title' => 'Title',
            'slug' => 'slug-demo',
            'description' => 'Description',
            'categories' => 'news,updates',
            'keywords' => 'foo,bar',
            'viewportWidth' => '1200',
        ];
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

        // Added for compatibility with attribute tests
        public function make($abstract, array $parameters = [])
        {
            return $this->get($abstract);
        }

        public function resolve(string $serviceClass): ?object
        {
            return $this->get($serviceClass);
        }

        public function has(string $serviceClass): bool
        {
            return isset($this->services[$serviceClass]);
        }

        public function instance($abstract, $instance)
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

// Mock WP_Screen class for WordPress tests
if (! class_exists('WP_Screen')) {
    class WP_Screen
    {
        public $id = 'woocommerce_page_wc-status';
        public $base = 'woocommerce_page_wc-status';
        
        public function __construct()
        {
            // Mock implementation
        }
    }
}

// Laravel response helper function
if (! function_exists('response')) {
    function response($content = '', $status = 200, array $headers = [])
    {
        return new \Illuminate\Http\Response($content, $status, $headers);
    }
}

/**
 * Create a real Template instance for testing since it's a readonly final class.
 */
function createTestTemplate(string $path = '/test/template.php', bool $isBladeTemplate = false): \Pollora\Plugins\WooCommerce\Domain\Models\Template
{
    return new \Pollora\Plugins\WooCommerce\Domain\Models\Template($path, basename($path, '.php'), $isBladeTemplate);
}

/**
 * Create a mock WooCommerceService for testing.
 */
function createMockWooCommerceService(array $templates = []): object
{
    $service = Mockery::mock(\Pollora\Plugins\WooCommerce\Domain\Services\WooCommerceService::class);

    // Setup default behaviors
    $service->shouldReceive('getAllTemplatePaths')
        ->andReturn(['/path/to/woocommerce/templates/'])
        ->byDefault();

    $service->shouldReceive('getWooCommerceTemplatePath')
        ->andReturn('woocommerce/')
        ->byDefault();

    $service->shouldReceive('isWooCommerceStatusScreen')
        ->withAnyArgs()
        ->andReturn(false)
        ->byDefault();

    // Setup template creation
    foreach ($templates as $path => $template) {
        $service->shouldReceive('createTemplate')
            ->with($path)
            ->andReturn($template);
    }

    return $service;
}

/**
 * Create a mock WordPressWooCommerceAdapter for testing.
 */
function createMockWooCommerceAdapter(): object
{
    $adapter = Mockery::mock(\Pollora\Plugins\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter::class);

    // Setup default behaviors
    $adapter->shouldReceive('isAdmin')
        ->andReturn(false)
        ->byDefault();

    $adapter->shouldReceive('isDoingAjax')
        ->andReturn(false)
        ->byDefault();

    $adapter->shouldReceive('getCurrentScreen')
        ->andReturn(null)
        ->byDefault();

    $adapter->shouldReceive('locateTemplate')
        ->andReturn('')
        ->byDefault();

    $adapter->shouldReceive('addThemeSupport')
        ->andReturn(true)
        ->byDefault();

    return $adapter;
}

<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Response;
use Pollora\ThirdParty\WooCommerce\Domain\Models\Template;
use Pollora\ThirdParty\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\ThirdParty\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
|--------------------------------------------------------------------------
| Brain Monkey WordPress Stubs
|--------------------------------------------------------------------------
|
| Default WordPress function stubs using Brain Monkey. Called automatically
| via TestCase::setUp() for Unit tests and Pest beforeEach for Feature tests.
|
*/

/**
 * Setup default WordPress function stubs using Brain Monkey.
 */
function setupDefaultWordPressStubs(): void
{
    // Provide a passthrough translator so Laravel's __() works without a full app.
    // Must accept mixed $replace because WordPress-style __('text', 'domain')
    // passes a string where Laravel expects an array.
    Container::getInstance()->instance('translator', new PassthroughTranslator);

    // WordPress-specific translation functions
    foreach (['_x', '_n', '_nx', 'esc_html__', 'esc_html_x', 'esc_attr__', 'esc_attr_x'] as $fn) {
        \Brain\Monkey\Functions\when($fn)->returnArg();
    }

    // Escaping functions
    \Brain\Monkey\Functions\stubEscapeFunctions();

    // Hook functions
    \Brain\Monkey\Functions\stubs([
        'add_filter' => true,
        'add_action' => true,
        'remove_filter' => true,
        'register_rest_route' => true,
    ]);
    \Brain\Monkey\Functions\when('apply_filters')->alias(fn ($tag, $value) => $value);

    // Option functions
    \Brain\Monkey\Functions\stubs([
        'add_option' => true,
        'update_option' => true,
        'delete_option' => true,
    ]);
    \Brain\Monkey\Functions\when('get_option')->alias(fn ($option, $default = false) => $default);

    // Theme functions
    \Brain\Monkey\Functions\stubs([
        'locate_template' => '',
        'add_theme_support' => true,
        'is_child_theme' => false,
        'get_stylesheet_directory' => '/path/to/theme',
        'get_stylesheet' => 'test-theme',
        'get_template_directory' => '/theme',
        'get_theme_file_path' => '/theme',
        'current_theme_supports' => false,
        'wp_is_block_theme' => false,
    ]);
    \Brain\Monkey\Functions\when('get_body_class')->justReturn(['page']);

    // Admin functions
    \Brain\Monkey\Functions\stubs([
        'is_admin' => false,
        'wp_doing_ajax' => false,
        'doing_action' => false,
    ]);
    \Brain\Monkey\Functions\when('get_current_screen')->alias(fn () => new WP_Screen);

    // Conditional functions
    \Brain\Monkey\Functions\stubs([
        'is_page' => true,
        'is_singular' => true,
        'is_archive' => false,
        'is_404' => false,
        'is_search' => false,
        'is_category' => false,
        'is_tag' => false,
        'is_tax' => false,
        'is_front_page' => false,
        'is_home' => false,
        'is_single' => false,
        'is_author' => false,
        'is_date' => false,
        'is_page_template' => false,
        'is_attachment' => false,
        'is_embed' => false,
        'is_privacy_policy' => false,
        'is_post_type_archive' => false,
    ]);

    // Request-related conditionals
    \Brain\Monkey\Functions\stubs([
        'wp_using_themes' => true,
        'is_robots' => false,
        'is_favicon' => false,
        'is_feed' => false,
        'is_trackback' => false,
        'do_feed' => true,
    ]);

    // Template hierarchy functions
    \Brain\Monkey\Functions\stubs(array_fill_keys([
        'get_embed_template', 'get_404_template', 'get_search_template',
        'get_front_page_template', 'get_home_template', 'get_privacy_policy_template',
        'get_post_type_archive_template', 'get_taxonomy_template', 'get_attachment_template',
        'get_single_template', 'get_page_template', 'get_singular_template',
        'get_category_template', 'get_tag_template', 'get_author_template',
        'get_date_template', 'get_archive_template', 'get_index_template',
    ], ''));

    // Scheduling functions
    \Brain\Monkey\Functions\stubs([
        'wp_next_scheduled' => false,
        'wp_schedule_event' => true,
    ]);

    // Query functions
    \Brain\Monkey\Functions\stubs(['get_page_template_slug' => 'template-custom.php']);
    \Brain\Monkey\Functions\when('get_queried_object')->alias(function (): stdClass {
        $obj = new stdClass;
        $obj->post_type = 'page';
        $obj->post_name = 'test-page';
        $obj->ID = 123;

        return $obj;
    });
    \Brain\Monkey\Functions\when('get_post')->alias(function (): stdClass {
        $post = new stdClass;
        $post->post_name = 'parent-page';
        $post->post_parent = 0;

        return $post;
    });
    \Brain\Monkey\Functions\when('get_query_var')->alias(fn ($var): string => $var === 'post_type' ? 'page' : '');
    \Brain\Monkey\Functions\when('get_post_meta')->justReturn('');

    // WooCommerce
    \Brain\Monkey\Functions\stubs(['WC' => null]);

    // Transient functions
    \Brain\Monkey\Functions\stubs([
        'get_transient' => false,
        'set_transient' => true,
    ]);

    // HTTP functions
    \Brain\Monkey\Functions\when('wp_remote_get')->alias(fn () => new WP_Error);
    \Brain\Monkey\Functions\stubs(['wp_remote_retrieve_body' => '']);
    \Brain\Monkey\Functions\when('is_wp_error')->alias(fn ($thing): bool => $thing instanceof WP_Error);

    // User functions
    \Brain\Monkey\Functions\stubs([
        'get_current_user_id' => 0,
        'get_user_meta' => '',
        'update_user_meta' => true,
    ]);

    // Security functions
    \Brain\Monkey\Functions\stubs([
        'wp_create_nonce' => 'test-nonce',
        'check_ajax_referer' => true,
        'wp_die' => null,
    ]);
    \Brain\Monkey\Functions\when('sanitize_text_field')->alias(fn ($str): string => trim(strip_tags((string) $str)));

    // Cache functions
    \Brain\Monkey\Functions\stubs([
        'wp_cache_get' => false,
        'wp_cache_add' => true,
    ]);

    // Text functions
    \Brain\Monkey\Functions\when('translate')->alias(fn ($text) => $text);
    \Brain\Monkey\Functions\when('translate_with_gettext_context')->alias(fn ($text) => $text);
    \Brain\Monkey\Functions\when('_cleanup_header_comment')->alias(fn ($str): string => trim((string) $str));
    \Brain\Monkey\Functions\when('sanitize_key')->alias(fn ($key): string => strtolower(trim((string) $key)));
    \Brain\Monkey\Functions\when('wp_parse_args')->alias(fn ($args, $defaults = []): array => array_merge($defaults, (array) $args));
    \Brain\Monkey\Functions\when('get_file_data')->alias(fn ($file, $headers = []): array => [
        'title' => 'Title',
        'slug' => 'slug-demo',
        'description' => 'Description',
        'categories' => 'news,updates',
        'keywords' => 'foo,bar',
        'viewportWidth' => '1200',
    ]);

    // Simple WordPress utility functions
    \Brain\Monkey\Functions\stubs([
        '__return_true' => true,
        '__return_false' => false,
    ]);
}

/*
|--------------------------------------------------------------------------
| Passthrough Translator
|--------------------------------------------------------------------------
|
| Handles both Laravel-style __($key, $replace[], $locale) and
| WordPress-style __($text, $domain) calls by returning the key as-is.
|
*/

if (! class_exists('PassthroughTranslator')) {
    class PassthroughTranslator
    {
        public function get($key, mixed $replace = [], $locale = null): string
        {
            return $key;
        }

        public function choice($key, $number, array $replace = [], $locale = null): string
        {
            return $key;
        }

        public function getLocale(): string
        {
            return 'en';
        }

        public function setLocale($locale): void {}
    }
}

/*
|--------------------------------------------------------------------------
| Mock WordPress Classes
|--------------------------------------------------------------------------
*/

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct()
        {
            // Mock implementation
        }
    }
}

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

/*
|--------------------------------------------------------------------------
| Test Helper Classes
|--------------------------------------------------------------------------
*/

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

if (! class_exists('TestContainer')) {
    class TestContainer
    {
        public function __construct(private array $services = []) {}

        public function get(string $serviceClass): ?object
        {
            return $this->services[$serviceClass] ?? null;
        }

        public function make(string $abstract, array $parameters = []): ?object
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

        public function instance($abstract, $instance): void
        {
            $this->services[$abstract] = $instance;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Laravel Helper Functions
|--------------------------------------------------------------------------
*/

if (! function_exists('app')) {
    /**
     * @param  string|null  $abstract
     * @return mixed|Application
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
    function app_path(?string $path = ''): string
    {
        return __DIR__.'/../../app/'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}

if (! function_exists('config_path')) {
    function config_path(?string $path = ''): string
    {
        return __DIR__.'/../../config/'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}

if (! function_exists('base_path')) {
    function base_path(?string $path = ''): string
    {
        return __DIR__.'/../..'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}

if (! function_exists('response')) {
    function response($content = '', $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers);
    }
}

if (! function_exists('abort')) {
    function abort($code, $message = ''): void
    {
        throw new HttpException($code, $message);
    }
}

if (! function_exists('route_condition_test')) {
    function route_condition_test($param = null): bool
    {
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Test Factory Functions
|--------------------------------------------------------------------------
*/

/**
 * Create a real Template instance for testing.
 */
function createTestTemplate(string $path = '/test/template.php', bool $isBladeTemplate = false): Template
{
    return new Template($path, basename($path, '.php'), $isBladeTemplate);
}

/**
 * Create a mock WooCommerceService for testing.
 */
function createMockWooCommerceService(array $templates = []): object
{
    $service = Mockery::mock(WooCommerceService::class);

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
    $adapter = Mockery::mock(WordPressWooCommerceAdapter::class);

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

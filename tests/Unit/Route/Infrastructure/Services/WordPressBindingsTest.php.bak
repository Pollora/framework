<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;

/**
 * Tests for WordPress bindings in ExtendedRouter.
 * 
 * This test suite verifies that the router correctly binds
 * WordPress context-specific data to route parameters based
 * on the current WordPress state.
 */
class WordPressBindingsTest extends TestCase
{
    private ExtendedRouter $router;
    private Route $route;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize WordPress mocks
        setupWordPressMocks();
        
        $container = new Container();
        $dispatcher = $this->createMock(Dispatcher::class);
        
        $this->router = new ExtendedRouter($dispatcher, $container);
        $this->route = new Route(['GET'], '/test', function () { return 'test'; });
    }

    protected function tearDown(): void
    {
        resetWordPressMocks();
        parent::tearDown();
    }

    public function test_core_wordpress_globals_are_always_bound(): void
    {
        // Set up basic WordPress globals
        global $post, $wp_query, $wp;
        $post = (object) ['ID' => 123, 'post_title' => 'Test Post'];
        $wp_query = (object) ['is_main_query' => true, 'found_posts' => 10];
        $wp = (object) ['query_vars' => []];

        $this->router->addWordPressBindings($this->route);

        // Check that core globals are bound
        $this->assertEquals($post, $this->route->parameter('post'));
        $this->assertEquals($wp_query, $this->route->parameter('wp_query'));
        $this->assertEquals($wp, $this->route->parameter('wp'));
    }

    public function test_queried_object_is_bound_when_available(): void
    {
        // Mock get_queried_object to return a post
        setWordPressFunction('get_queried_object', fn() => (object) [
            'ID' => 123,
            'post_title' => 'Test Post',
            'post_type' => 'post'
        ]);

        $this->router->addWordPressBindings($this->route);

        // Check that queried object is bound
        $this->assertNotNull($this->route->parameter('queried_object'));
        $this->assertNotNull($this->route->parameter('queried_post'));
        $this->assertEquals(123, $this->route->parameter('queried_object')->ID);
    }

    public function test_page_bindings(): void
    {
        // Mock page context
        setWordPressFunction('is_page', fn() => true);
        setWordPressFunction('get_queried_object', fn() => (object) [
            'ID' => 456,
            'post_title' => 'About Page',
            'post_type' => 'page'
        ]);
        setWordPressFunction('get_queried_object_id', fn() => 456);
        setWordPressFunction('get_page_template_slug', fn($id) => 'page-about.php');
        setWordPressFunction('get_post_ancestors', fn($id) => [123, 789]);

        $this->router->addWordPressBindings($this->route);

        // Check page specific bindings
        $this->assertNotNull($this->route->parameter('current_page'));
        $this->assertEquals('page-about.php', $this->route->parameter('page_template'));
        $this->assertIsArray($this->route->parameter('page_ancestors'));
    }

    public function test_category_archive_bindings(): void
    {
        // Mock category context
        setWordPressFunction('is_category', fn() => true);
        setWordPressFunction('get_queried_object', fn() => (object) [
            'term_id' => 5,
            'name' => 'News',
            'slug' => 'news',
            'taxonomy' => 'category'
        ]);
        setWordPressFunction('get_category_parents', fn($id, $link, $separator, $nicename) => 'Parent / News');

        $this->router->addWordPressBindings($this->route);

        // Check category specific bindings
        $category = $this->route->parameter('current_category');
        $this->assertNotNull($category);
        $this->assertEquals('News', $category->name);
        $this->assertEquals($category, $this->route->parameter('category')); // Alias
        $this->assertNotNull($this->route->parameter('category_parents'));
    }

    public function test_tag_archive_bindings(): void
    {
        // Mock tag context
        setWordPressFunction('is_tag', fn() => true);
        setWordPressFunction('get_queried_object', fn() => (object) [
            'term_id' => 8,
            'name' => 'PHP',
            'slug' => 'php',
            'taxonomy' => 'post_tag'
        ]);

        $this->router->addWordPressBindings($this->route);

        // Check tag specific bindings
        $tag = $this->route->parameter('current_tag');
        $this->assertNotNull($tag);
        $this->assertEquals('PHP', $tag->name);
        $this->assertEquals($tag, $this->route->parameter('tag')); // Alias
    }

    public function test_custom_taxonomy_bindings(): void
    {
        // Mock custom taxonomy context
        setWordPressFunction('is_tax', fn() => true);
        setWordPressFunction('get_queried_object', fn() => (object) [
            'term_id' => 10,
            'name' => 'Web Development',
            'slug' => 'web-development',
            'taxonomy' => 'portfolio_category'
        ]);
        setWordPressFunction('get_term_parents_list', fn($id, $taxonomy) => '<a href="#">Web</a> > Web Development');

        $this->router->addWordPressBindings($this->route);

        // Check taxonomy specific bindings
        $term = $this->route->parameter('current_term');
        $this->assertNotNull($term);
        $this->assertEquals('Web Development', $term->name);
        $this->assertEquals('portfolio_category', $this->route->parameter('taxonomy'));
        $this->assertEquals($term, $this->route->parameter('term')); // Alias
        $this->assertNotNull($this->route->parameter('term_parents'));
    }

    public function test_author_archive_bindings(): void
    {
        // Mock author context
        setWordPressFunction('is_author', fn() => true);
        setWordPressFunction('get_queried_object', fn() => (object) [
            'ID' => 2,
            'user_login' => 'john_doe',
            'display_name' => 'John Doe'
        ]);
        setWordPressFunction('get_queried_object_id', fn() => 2);
        setWordPressFunction('get_the_author_meta', function($field, $user_id) {
            return match($field) {
                'user_post_count' => 15,
                'display_name' => 'John Doe',
                default => ''
            };
        });

        $this->router->addWordPressBindings($this->route);

        // Check author specific bindings
        $author = $this->route->parameter('current_author');
        $this->assertNotNull($author);
        $this->assertEquals('John Doe', $author->display_name);
        $this->assertEquals($author, $this->route->parameter('author')); // Alias
        $this->assertEquals(15, $this->route->parameter('author_posts_count'));
        $this->assertEquals('John Doe', $this->route->parameter('author_display_name'));
    }

    public function test_date_archive_bindings(): void
    {
        // Mock date archive context
        setWordPressFunction('is_date', fn() => true);
        setWordPressFunction('is_year', fn() => false);
        setWordPressFunction('is_month', fn() => true);
        setWordPressFunction('is_day', fn() => false);
        setWordPressFunction('get_query_var', function($var) {
            return match($var) {
                'year' => '2024',
                'monthnum' => '03',
                'day' => '',
                default => ''
            };
        });

        $this->router->addWordPressBindings($this->route);

        // Check date archive specific bindings
        $this->assertEquals('2024', $this->route->parameter('archive_year'));
        $this->assertEquals('03', $this->route->parameter('archive_month'));
        $this->assertEquals('month', $this->route->parameter('archive_type'));
    }

    public function test_search_bindings(): void
    {
        // Mock search context
        setWordPressFunction('is_search', fn() => true);
        setWordPressFunction('get_search_query', fn() => 'wordpress development');
        setWordPressFunction('get_query_var', function($var, $default = '') {
            return match($var) {
                'found_posts' => 25,
                default => $default
            };
        });

        $this->router->addWordPressBindings($this->route);

        // Check search specific bindings
        $this->assertEquals('wordpress development', $this->route->parameter('search_query'));
        $this->assertEquals(25, $this->route->parameter('search_results_count'));
    }

    public function test_post_type_archive_bindings(): void
    {
        // Mock post type archive context
        setWordPressFunction('is_archive', fn() => true);
        setWordPressFunction('is_post_type_archive', fn() => true);
        setWordPressFunction('get_query_var', function($var) {
            return match($var) {
                'post_type' => 'portfolio',
                default => ''
            };
        });

        $this->router->addWordPressBindings($this->route);

        // Check post type archive specific bindings
        $this->assertEquals('portfolio', $this->route->parameter('post_type_archive'));
    }

    public function test_only_non_null_values_are_bound(): void
    {
        // Mock context where some functions return null
        setWordPressFunction('is_single', fn() => true);
        setWordPressFunction('get_queried_object', fn() => null);
        setWordPressFunction('get_post_type', fn() => null);

        $this->router->addWordPressBindings($this->route);

        // Parameters should not exist if the values are null
        $this->assertFalse($this->route->hasParameter('current_post'));
        $this->assertFalse($this->route->hasParameter('post_type'));
    }

    public function test_multiple_contexts_can_coexist(): void
    {
        // Mock multiple contexts (e.g., single post that's also in an archive)
        setWordPressFunction('is_single', fn() => true);
        setWordPressFunction('is_archive', fn() => true);
        setWordPressFunction('get_queried_object', fn() => (object) [
            'ID' => 123,
            'post_title' => 'Test Post'
        ]);

        $this->router->addWordPressBindings($this->route);

        // Both single and archive bindings should be present
        $this->assertNotNull($this->route->parameter('current_post'));
        // Archive bindings might also be present depending on the specific context
    }
}
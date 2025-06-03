<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;

/**
 * Tests for WordPress dependency injection in ExtendedRouter.
 * 
 * This test suite verifies that the router correctly injects
 * WordPress objects based on type hints in route callbacks.
 */
class DependencyInjectionTest extends TestCase
{
    private ExtendedRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize WordPress mocks
        setupWordPressMocks();
        
        $container = new Container();
        $dispatcher = $this->createMock(Dispatcher::class);
        
        $this->router = new ExtendedRouter($dispatcher, $container);
    }

    protected function tearDown(): void
    {
        resetWordPressMocks();
        parent::tearDown();
    }

    public function test_wp_post_injection_from_global(): void
    {
        // Set up global post
        global $post;
        $post = $this->createMockWPPost(123, 'Test Post');

        $route = new Route(['GET'], '/test', function (\WP_Post $post) {
            return $post->post_title;
        });

        $this->router->addWordPressBindings($route);

        $this->assertInstanceOf(\WP_Post::class, $route->parameter('post'));
        $this->assertEquals('Test Post', $route->parameter('post')->post_title);
    }

    public function test_wp_post_injection_from_queried_object(): void
    {
        // Mock get_queried_object to return a post
        $mockPost = $this->createMockWPPost(456, 'Queried Post');
        setWordPressFunction('get_queried_object', fn() => $mockPost);

        $route = new Route(['GET'], '/test', function (\WP_Post $post) {
            return $post->post_title;
        });

        $this->router->addWordPressBindings($route);

        $this->assertInstanceOf(\WP_Post::class, $route->parameter('post'));
        $this->assertEquals('Queried Post', $route->parameter('post')->post_title);
    }

    public function test_wp_term_injection(): void
    {
        // Mock get_queried_object to return a term
        $mockTerm = $this->createMockWPTerm(5, 'Test Category', 'category');
        setWordPressFunction('get_queried_object', fn() => $mockTerm);

        $route = new Route(['GET'], '/test', function (\WP_Term $term) {
            return $term->name;
        });

        $this->router->addWordPressBindings($route);

        $this->assertInstanceOf(\WP_Term::class, $route->parameter('term'));
        $this->assertEquals('Test Category', $route->parameter('term')->name);
    }

    public function test_wp_user_injection(): void
    {
        // Mock get_queried_object to return a user
        $mockUser = $this->createMockWPUser(2, 'john_doe', 'John Doe');
        setWordPressFunction('get_queried_object', fn() => $mockUser);

        $route = new Route(['GET'], '/test', function (\WP_User $user) {
            return $user->display_name;
        });

        $this->router->addWordPressBindings($route);

        $this->assertInstanceOf(\WP_User::class, $route->parameter('user'));
        $this->assertEquals('John Doe', $route->parameter('user')->display_name);
    }

    public function test_wp_query_injection(): void
    {
        // Set up global wp_query
        global $wp_query;
        $wp_query = (object) [
            'is_main_query' => true,
            'found_posts' => 10,
            'posts' => []
        ];

        $route = new Route(['GET'], '/test', function (\WP_Query $query) {
            return $query->found_posts;
        });

        $this->router->addWordPressBindings($route);

        $this->assertEquals($wp_query, $route->parameter('query'));
    }

    public function test_multiple_type_injection(): void
    {
        // Set up multiple WordPress objects
        global $post, $wp_query;
        $post = $this->createMockWPPost(123, 'Test Post');
        $wp_query = (object) ['found_posts' => 5];

        $mockTerm = $this->createMockWPTerm(3, 'Test Tag', 'post_tag');
        setWordPressFunction('get_queried_object', fn() => $mockTerm);

        $route = new Route(['GET'], '/test', function (\WP_Post $post, \WP_Term $term, \WP_Query $query) {
            return [$post->post_title, $term->name, $query->found_posts];
        });

        $this->router->addWordPressBindings($route);

        $this->assertInstanceOf(\WP_Post::class, $route->parameter('post'));
        $this->assertInstanceOf(\WP_Term::class, $route->parameter('term'));
        $this->assertEquals($wp_query, $route->parameter('query'));
    }

    public function test_controller_method_injection(): void
    {
        $mockPost = $this->createMockWPPost(789, 'Controller Post');
        setWordPressFunction('get_queried_object', fn() => $mockPost);

        // Test with controller@method format
        $route = new Route(['GET'], '/test', TestController::class . '@show');

        $this->router->addWordPressBindings($route);

        $this->assertInstanceOf(\WP_Post::class, $route->parameter('post'));
        $this->assertEquals('Controller Post', $route->parameter('post')->post_title);
    }

    public function test_invokable_controller_injection(): void
    {
        $mockTerm = $this->createMockWPTerm(10, 'Invokable Term', 'category');
        setWordPressFunction('get_queried_object', fn() => $mockTerm);

        // Test with invokable controller
        $route = new Route(['GET'], '/test', InvokableTestController::class);

        $this->router->addWordPressBindings($route);

        $this->assertInstanceOf(\WP_Term::class, $route->parameter('term'));
        $this->assertEquals('Invokable Term', $route->parameter('term')->name);
    }

    public function test_non_wordpress_types_are_ignored(): void
    {
        $route = new Route(['GET'], '/test', function (string $name, int $id, \DateTime $date) {
            return 'test';
        });

        $this->router->addWordPressBindings($route);

        // Should not have any parameters bound
        $this->assertFalse($route->hasParameter('name'));
        $this->assertFalse($route->hasParameter('id'));
        $this->assertFalse($route->hasParameter('date'));
    }

    public function test_null_types_when_objects_unavailable(): void
    {
        // Clear global post and mock functions to return null
        global $post;
        $post = null;
        setWordPressFunction('get_queried_object', fn() => null);

        $route = new Route(['GET'], '/test', function (\WP_Post $post = null, \WP_Term $term = null) {
            return 'test';
        });

        $this->router->addWordPressBindings($route);

        // Should not bind null values
        $this->assertFalse($route->hasParameter('post'));
        $this->assertFalse($route->hasParameter('term'));
    }

    public function test_custom_parameter_names(): void
    {
        $mockPost = $this->createMockWPPost(999, 'Custom Named Post');
        setWordPressFunction('get_queried_object', fn() => $mockPost);

        $route = new Route(['GET'], '/test', function (\WP_Post $customPost, \WP_Post $anotherPost) {
            return 'test';
        });

        $this->router->addWordPressBindings($route);

        // Both parameters should get the same post object
        $this->assertInstanceOf(\WP_Post::class, $route->parameter('customPost'));
        $this->assertInstanceOf(\WP_Post::class, $route->parameter('anotherPost'));
        $this->assertEquals('Custom Named Post', $route->parameter('customPost')->post_title);
        $this->assertEquals('Custom Named Post', $route->parameter('anotherPost')->post_title);
    }

    // Helper methods to create mock WordPress objects
    private function createMockWPPost(int $id, string $title): \WP_Post
    {
        $post = new \WP_Post((object) [
            'ID' => $id,
            'post_title' => $title,
            'post_type' => 'post',
            'post_status' => 'publish'
        ]);
        
        return $post;
    }

    private function createMockWPTerm(int $id, string $name, string $taxonomy): \WP_Term
    {
        return new \WP_Term((object) [
            'term_id' => $id,
            'name' => $name,
            'slug' => sanitize_title($name),
            'taxonomy' => $taxonomy,
            'count' => 5
        ]);
    }

    private function createMockWPUser(int $id, string $login, string $displayName): \WP_User
    {
        $user = new \WP_User();
        $user->ID = $id;
        $user->user_login = $login;
        $user->display_name = $displayName;
        
        return $user;
    }
}

// Test controller classes for testing controller injection
class TestController
{
    public function show(\WP_Post $post)
    {
        return $post;
    }
}

class InvokableTestController
{
    public function __invoke(\WP_Term $term)
    {
        return $term;
    }
}
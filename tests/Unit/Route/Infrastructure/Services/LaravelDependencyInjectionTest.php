<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Services;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Pollora\Route\Infrastructure\Providers\WordPressBindingServiceProvider;
use WP_Post;
use WP_Term;
use WP_User;
use WP_Query;

/**
 * Tests for Laravel's native dependency injection with WordPress types.
 * 
 * This test verifies that WordPress types are properly registered with
 * Laravel's container and can be resolved automatically.
 */
class LaravelDependencyInjectionTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize WordPress mocks
        setupWordPressMocks();
        
        $this->container = new Container();
        
        // Register the WordPress binding service provider
        $provider = new WordPressBindingServiceProvider($this->container);
        $provider->register();
    }

    protected function tearDown(): void
    {
        resetWordPressMocks();
        parent::tearDown();
    }

    public function test_wp_post_can_be_resolved_from_container(): void
    {
        // Set up global post
        global $post;
        $post = $this->createMockWPPost(123, 'Test Post');

        $resolvedPost = $this->container->make(WP_Post::class);

        $this->assertInstanceOf(WP_Post::class, $resolvedPost);
        $this->assertEquals('Test Post', $resolvedPost->post_title);
        $this->assertEquals(123, $resolvedPost->ID);
    }

    public function test_wp_term_can_be_resolved_from_container(): void
    {
        $mockTerm = $this->createMockWPTerm(5, 'Test Category', 'category');
        setWordPressFunction('get_queried_object', fn() => $mockTerm);

        $resolvedTerm = $this->container->make(WP_Term::class);

        $this->assertInstanceOf(WP_Term::class, $resolvedTerm);
        $this->assertEquals('Test Category', $resolvedTerm->name);
        $this->assertEquals(5, $resolvedTerm->term_id);
    }

    public function test_wp_user_can_be_resolved_from_container(): void
    {
        $mockUser = $this->createMockWPUser(2, 'john_doe', 'John Doe');
        setWordPressFunction('get_queried_object', fn() => $mockUser);

        $resolvedUser = $this->container->make(WP_User::class);

        $this->assertInstanceOf(WP_User::class, $resolvedUser);
        $this->assertEquals('John Doe', $resolvedUser->display_name);
        $this->assertEquals(2, $resolvedUser->ID);
    }

    public function test_wp_query_can_be_resolved_from_container(): void
    {
        global $wp_query;
        $wp_query = (object) [
            'is_main_query' => true,
            'found_posts' => 10,
            'posts' => []
        ];

        $resolvedQuery = $this->container->make(WP_Query::class);

        $this->assertEquals($wp_query, $resolvedQuery);
        $this->assertEquals(10, $resolvedQuery->found_posts);
    }

    public function test_wp_can_be_resolved_from_container(): void
    {
        global $wp;
        $wp = (object) ['query_vars' => ['test' => 'value']];

        $resolvedWp = $this->container->make(\WP::class);

        $this->assertEquals($wp, $resolvedWp);
    }

    public function test_null_values_are_handled_gracefully(): void
    {
        // Clear all globals and mock functions
        global $post, $wp_query, $wp;
        $post = null;
        $wp_query = null;
        $wp = null;
        
        setWordPressFunction('get_queried_object', fn() => null);
        setWordPressFunction('wp_get_current_user', fn() => null);

        // Should return null without throwing exceptions
        $this->assertNull($this->container->make(WP_Post::class));
        $this->assertNull($this->container->make(WP_Term::class));
        $this->assertNull($this->container->make(WP_User::class));
        $this->assertNull($this->container->make(WP_Query::class));
        $this->assertNull($this->container->make(\WP::class));
    }

    public function test_container_can_resolve_controller_with_dependencies(): void
    {
        // Set up WordPress objects
        global $post, $wp_query;
        $post = $this->createMockWPPost(999, 'Controller Test Post');
        $wp_query = (object) ['found_posts' => 15];

        // Mock a controller class
        $controller = new class {
            public function __invoke(WP_Post $post, WP_Query $query): array
            {
                return [
                    'post_title' => $post->post_title,
                    'posts_count' => $query->found_posts
                ];
            }
        };

        // Resolve the controller method with dependencies
        $result = $this->container->call([$controller, '__invoke']);

        $this->assertEquals([
            'post_title' => 'Controller Test Post',
            'posts_count' => 15
        ], $result);
    }

    // Helper methods to create mock WordPress objects
    private function createMockWPPost(int $id, string $title): WP_Post
    {
        return new WP_Post((object) [
            'ID' => $id,
            'post_title' => $title,
            'post_type' => 'post',
            'post_status' => 'publish'
        ]);
    }

    private function createMockWPTerm(int $id, string $name, string $taxonomy): WP_Term
    {
        return new WP_Term((object) [
            'term_id' => $id,
            'name' => $name,
            'slug' => sanitize_title($name),
            'taxonomy' => $taxonomy,
            'count' => 5
        ]);
    }

    private function createMockWPUser(int $id, string $login, string $displayName): WP_User
    {
        $user = new WP_User();
        $user->ID = $id;
        $user->user_login = $login;
        $user->display_name = $displayName;
        
        return $user;
    }
}
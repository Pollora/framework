<?php

declare(strict_types=1);

namespace Tests\Unit\Route\UI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\TestCase;
use Pollora\Route\UI\Http\Controllers\FrontendController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FrontendControllerTest extends TestCase
{
    private FrontendController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->controller = new FrontendController();
    }

    public function test_it_returns_view_when_template_exists(): void
    {
        // Mock WordPress functions
        if (!function_exists('is_single')) {
            eval('function is_single() { return true; }');
        }
        if (!function_exists('get_post')) {
            eval('function get_post() { return (object)[\'post_type\' => \'post\', \'post_name\' => \'test\']; }');
        }
        
        // Mock View facade
        View::shouldReceive('exists')
            ->with('single-post-test')
            ->once()
            ->andReturn(true);
        
        View::shouldReceive('make')
            ->with('single-post-test')
            ->once()
            ->andReturn('template content');
        
        $request = Request::create('/test');
        $response = $this->controller->handle($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('template content', $response->getContent());
    }

    public function test_it_falls_back_through_template_hierarchy(): void
    {
        // Mock WordPress functions
        if (!function_exists('is_page')) {
            eval('function is_page() { return true; }');
        }
        if (!function_exists('get_post')) {
            eval('function get_post() { return (object)[\'post_name\' => \'about\', \'ID\' => 123]; }');
        }
        if (!function_exists('get_page_template_slug')) {
            eval('function get_page_template_slug() { return \'\'; }');
        }
        
        // Mock View facade - first template doesn't exist, second does
        View::shouldReceive('exists')
            ->with('page-about')
            ->once()
            ->andReturn(false);
        
        View::shouldReceive('exists')
            ->with('page-123')
            ->once()
            ->andReturn(false);
        
        View::shouldReceive('exists')
            ->with('page')
            ->once()
            ->andReturn(true);
        
        View::shouldReceive('make')
            ->with('page')
            ->once()
            ->andReturn('page template');
        
        $request = Request::create('/about');
        $response = $this->controller->handle($request);
        
        $this->assertEquals('page template', $response->getContent());
    }

    public function test_it_throws_404_when_no_template_found(): void
    {
        // Mock WordPress functions to return false
        if (!function_exists('is_404')) {
            eval('function is_404() { return true; }');
        }
        
        // Mock View facade - no templates exist
        View::shouldReceive('exists')
            ->andReturn(false);
        
        $this->expectException(NotFoundHttpException::class);
        
        $request = Request::create('/nonexistent');
        $this->controller->handle($request);
    }

    public function test_it_applies_wordpress_filters_when_available(): void
    {
        // Mock WordPress functions
        if (!function_exists('is_home')) {
            eval('function is_home() { return true; }');
        }
        if (!function_exists('apply_filters')) {
            eval('function apply_filters($tag, $value) { 
                if ($tag === "home_template_hierarchy") {
                    return ["custom-home", "home", "index"];
                }
                return $value;
            }');
        }
        
        // Mock View facade
        View::shouldReceive('exists')
            ->with('custom-home')
            ->once()
            ->andReturn(true);
        
        View::shouldReceive('make')
            ->with('custom-home')
            ->once()
            ->andReturn('custom home template');
        
        $request = Request::create('/');
        $response = $this->controller->handle($request);
        
        $this->assertEquals('custom home template', $response->getContent());
    }

    public function test_it_builds_correct_template_slug_for_different_contexts(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildTemplateSlug');
        $method->setAccessible(true);
        
        // Test category
        if (!function_exists('is_category')) {
            eval('function is_category() { return true; }');
        }
        if (!function_exists('get_queried_object')) {
            eval('function get_queried_object() { return (object)[\'slug\' => \'news\']; }');
        }
        
        $slug = $method->invoke($this->controller);
        $this->assertEquals('category-news', $slug);
    }

    public function test_it_gets_correct_template_type_for_filters(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getTemplateType');
        $method->setAccessible(true);
        
        // Mock search context
        if (!function_exists('is_search')) {
            eval('function is_search() { return true; }');
        }
        
        $type = $method->invoke($this->controller);
        $this->assertEquals('search', $type);
    }
}
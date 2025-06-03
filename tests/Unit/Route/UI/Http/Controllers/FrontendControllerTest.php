<?php

declare(strict_types=1);

namespace Tests\Unit\Route\UI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Pollora\Route\UI\Http\Controllers\FrontendController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * @covers \Pollora\Route\UI\Http\Controllers\FrontendController
 */
class FrontendControllerTest extends TestCase
{
    private FrontendController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        setupWordPressMocks();
        $this->controller = new FrontendController;
    }

    public function test_handle_aborts_when_themes_disabled(): void
    {
        // Mock wp_using_themes to return false
        setWordPressFunction('wp_using_themes', fn () => false);

        $request = Request::create('/test');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Themes are disabled');

        $this->controller->handle($request);
    }

    public function test_handle_continues_when_themes_enabled(): void
    {
        // Mock wp_using_themes to return true
        setWordPressFunction('wp_using_themes', fn () => true);

        // Mock WordPress conditional functions for index template
        setWordPressConditions([
            'is_single' => false,
            'is_page' => false,
            'is_category' => false,
            'is_tag' => false,
            'is_tax' => false,
            'is_author' => false,
            'is_date' => false,
            'is_post_type_archive' => false,
            'is_search' => false,
            'is_404' => false,
            'is_front_page' => false,
            'is_home' => false,
        ]);

        // Mock View to return a template
        View::shouldReceive('exists')
            ->with('index')
            ->andReturn(true);

        View::shouldReceive('make')
            ->with('index')
            ->andReturn('Template content');

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_wp_using_themes_check_works(): void
    {
        // Test that wp_using_themes is properly checked
        $this->assertTrue(wp_using_themes());

        // Set it to false
        setWordPressFunction('wp_using_themes', fn () => false);
        $this->assertFalse(wp_using_themes());

        // Verify the function is accessible
        $this->assertTrue(function_exists('wp_using_themes'));
    }

    public function test_build_template_slug_returns_single_for_single_post(): void
    {
        setWordPressConditions([
            'is_single' => true,
        ]);

        // Mock get_post to return null so it falls back to 'single'
        setWordPressFunction('get_post', fn () => null);

        $method = new \ReflectionMethod($this->controller, 'buildTemplateSlug');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller);

        $this->assertEquals('single', $result);
    }

    public function test_build_template_slug_returns_page_for_page(): void
    {
        setWordPressConditions([
            'is_single' => false,
            'is_page' => true,
        ]);

        // Mock get_post to return null so it falls back to 'page'
        setWordPressFunction('get_post', fn () => null);

        $method = new \ReflectionMethod($this->controller, 'buildTemplateSlug');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller);

        $this->assertEquals('page', $result);
    }

    public function test_build_template_slug_returns_index_for_default(): void
    {
        setWordPressConditions([
            'is_single' => false,
            'is_page' => false,
            'is_category' => false,
            'is_tag' => false,
            'is_tax' => false,
            'is_author' => false,
            'is_date' => false,
            'is_post_type_archive' => false,
            'is_search' => false,
            'is_404' => false,
            'is_front_page' => false,
            'is_home' => false,
        ]);

        $method = new \ReflectionMethod($this->controller, 'buildTemplateSlug');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller);

        $this->assertEquals('index', $result);
    }

    public function test_get_template_hierarchy_includes_index_fallback(): void
    {
        $method = new \ReflectionMethod($this->controller, 'getTemplateHierarchy');
        $method->setAccessible(true);

        $hierarchy = $method->invoke($this->controller, 'custom');

        $this->assertContains('index', $hierarchy);
        $this->assertEquals('index', end($hierarchy));
    }
}

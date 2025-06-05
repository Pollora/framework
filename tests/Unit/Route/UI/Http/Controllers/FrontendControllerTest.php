<?php

declare(strict_types=1);

namespace Tests\Unit\Route\UI\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Mockery;
use Pollora\Route\UI\Http\Controllers\FrontendController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * @covers \Pollora\Route\UI\Http\Controllers\FrontendController
 */
class FrontendControllerTest extends TestCase
{
    private FrontendController $controller;

    private Container $container;

    private ViewFactory $viewFactory;

    protected function setUp(): void
    {
        parent::setUp();
        setupWordPressMocks();

        $this->container = Mockery::mock(Container::class);
        $this->viewFactory = Mockery::mock(ViewFactory::class);
        $this->controller = new FrontendController($this->container, $this->viewFactory);
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

    public function test_handle_renders_blade_view_when_available(): void
    {
        // Mock wp_using_themes to return true
        setWordPressFunction('wp_using_themes', fn () => true);
        setWordPressFunction('is_page', fn () => true);
        setWordPressFunction('get_page_template', fn () => '/theme/page.php');
        setWordPressFunction('apply_filters', fn ($filter, $value) => $value);

        // Mock container to return blade view
        $this->container->shouldReceive('bound')
            ->with('pollora.view')
            ->andReturn(true);
        $this->container->shouldReceive('get')
            ->with('pollora.view')
            ->andReturn('templates.page');
        $this->container->shouldReceive('bound')
            ->with('pollora.data')
            ->andReturn(true);
        $this->container->shouldReceive('get')
            ->with('pollora.data')
            ->andReturn(['foo' => 'bar']);

        // Mock view factory
        $view = Mockery::mock(View::class);
        $view->shouldReceive('render')->andReturn('<html>Blade page content</html>');

        $this->viewFactory->shouldReceive('exists')
            ->with('templates.page')
            ->andReturn(true);
        $this->viewFactory->shouldReceive('make')
            ->with('templates.page', ['foo' => 'bar'])
            ->andReturn($view);

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('<html>Blade page content</html>', $response->getContent());
    }

    public function test_handle_falls_back_to_php_template(): void
    {
        // Mock wp_using_themes to return true
        setWordPressFunction('wp_using_themes', fn () => true);
        setWordPressFunction('is_page', fn () => true);
        setWordPressFunction('get_page_template', fn () => __FILE__);
        setWordPressFunction('apply_filters', fn ($filter, $value) => $value);

        // Mock container to not have blade view
        $this->container->shouldReceive('bound')
            ->with('pollora.view')
            ->andReturn(false);
        $this->container->shouldReceive('bound')
            ->with('pollora.data')
            ->andReturn(false);

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('<?php', $response->getContent());
    }

    public function test_handle_throws_404_when_no_template(): void
    {
        // Mock wp_using_themes to return true
        setWordPressFunction('wp_using_themes', fn () => true);

        // Mock all template functions to return empty
        setWordPressConditions([
            'is_page' => false,
            'is_singular' => false,
            'is_archive' => false,
            'is_404' => false,
            'is_search' => false,
            'is_front_page' => false,
            'is_home' => false,
            'is_privacy_policy' => false,
            'is_post_type_archive' => false,
            'is_tax' => false,
            'is_attachment' => false,
            'is_single' => false,
            'is_category' => false,
            'is_tag' => false,
            'is_author' => false,
            'is_date' => false,
            'is_embed' => false,
        ]);

        setWordPressFunction('get_index_template', fn () => '');
        setWordPressFunction('apply_filters', fn ($filter, $value) => $value);

        $this->container->shouldReceive('bound')
            ->with('pollora.view')
            ->andReturn(false);

        $request = Request::create('/test');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Template not found');

        $this->controller->handle($request);
    }
}

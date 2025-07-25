<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Pollora\Exceptions\Infrastructure\Services\ModuleAwareErrorViewResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Test suite for ModuleAwareErrorViewResolver.
 *
 * Tests the module-aware error view resolution functionality to ensure
 * proper prioritization of module error views over framework defaults.
 */
class ModuleAwareErrorViewResolverTest extends TestCase
{
    protected ModuleAwareErrorViewResolver $resolver;

    protected Container $container;

    protected ViewFactory $viewFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->createMock(Container::class);
        $this->viewFactory = $this->createMock(ViewFactory::class);

        $this->resolver = new ModuleAwareErrorViewResolver(
            $this->container,
            $this->viewFactory
        );
    }

    /**
     * Test that resolver returns correct error view for 404 status.
     */
    public function test_resolves_404_error_view(): void
    {
        $exception = new NotFoundHttpException('Not found');
        $request = Request::create('/test-path');

        $this->viewFactory
            ->expects($this->any())
            ->method('exists')
            ->willReturnCallback(fn ($view) => $view === 'errors.404');

        $result = $this->resolver->resolveErrorView($exception, $request, 404);

        $this->assertEquals('errors.404', $result);
    }

    /**
     * Test that resolver returns null when no error view exists.
     */
    public function test_returns_null_when_no_view_exists(): void
    {
        $exception = new HttpException(500, 'Server error');
        $request = Request::create('/test-path');

        $this->viewFactory
            ->expects($this->any())
            ->method('exists')
            ->willReturn(false);

        $result = $this->resolver->resolveErrorView($exception, $request, 500);

        $this->assertNull($result);
    }

    /**
     * Test that resolver tries fallback views for error categories.
     */
    public function test_tries_fallback_views_for_error_categories(): void
    {
        $exception = new HttpException(403, 'Forbidden');
        $request = Request::create('/test-path');

        $this->viewFactory
            ->expects($this->any())
            ->method('exists')
            ->willReturnCallback(fn ($view) => $view === 'errors.4xx');

        $result = $this->resolver->resolveErrorView($exception, $request, 403);

        $this->assertEquals('errors.4xx', $result);
    }

    /**
     * Test that resolver converts exception class to view name.
     */
    public function test_converts_exception_class_to_view_name(): void
    {
        $exception = new NotFoundHttpException('Not found');
        $request = Request::create('/test-path');

        $this->viewFactory
            ->expects($this->any())
            ->method('exists')
            ->willReturnCallback(fn ($view) => $view === 'errors.not-found-http');

        $result = $this->resolver->resolveErrorView($exception, $request, 404);

        $this->assertEquals('errors.not-found-http', $result);
    }

    /**
     * Test debug information generation returns empty in non-debug mode.
     */
    public function test_returns_empty_debug_info_when_debug_disabled(): void
    {
        // Mock config service to return debug = false
        $config = $this->createMock(\Illuminate\Contracts\Config\Repository::class);
        $config->expects($this->once())
            ->method('get')
            ->with('app.debug', false)
            ->willReturn(false);

        $this->container->expects($this->once())
            ->method('make')
            ->with('config')
            ->willReturn($config);

        $exception = new NotFoundHttpException('Not found');

        $debugInfo = $this->resolver->getDebugInfo(404, $exception);

        $this->assertEmpty($debugInfo);
    }

    /**
     * Test that kebab case conversion works correctly.
     */
    public function test_converts_pascal_case_to_kebab_case(): void
    {
        $reflection = new \ReflectionClass($this->resolver);
        $method = $reflection->getMethod('convertToKebabCase');
        $method->setAccessible(true);

        $this->assertEquals('not-found-http-exception', $method->invokeArgs($this->resolver, ['NotFoundHttpException']));
        $this->assertEquals('server-error', $method->invokeArgs($this->resolver, ['ServerError']));
        $this->assertEquals('test', $method->invokeArgs($this->resolver, ['Test']));
        $this->assertEquals('', $method->invokeArgs($this->resolver, ['']));
    }

    /**
     * Test that common suffixes are removed correctly.
     */
    public function test_removes_common_suffixes(): void
    {
        $reflection = new \ReflectionClass($this->resolver);
        $method = $reflection->getMethod('removeCommonSuffixes');
        $method->setAccessible(true);

        $this->assertEquals('NotFound', $method->invokeArgs($this->resolver, ['NotFoundException']));
        $this->assertEquals('Server', $method->invokeArgs($this->resolver, ['ServerError']));
        $this->assertEquals('NotFoundHttp', $method->invokeArgs($this->resolver, ['NotFoundHttpException']));
        $this->assertEquals('Test', $method->invokeArgs($this->resolver, ['Test']));
    }
}

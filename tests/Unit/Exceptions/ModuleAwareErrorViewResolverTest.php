<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Pollora\Exceptions\Infrastructure\Services\ModuleAwareErrorViewResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

describe('ModuleAwareErrorViewResolver', function (): void {
    beforeEach(function (): void {
        $this->container = Mockery::mock(Container::class);
        $this->viewFactory = Mockery::mock(ViewFactory::class);
        $this->resolver = new ModuleAwareErrorViewResolver($this->container, $this->viewFactory);
    });

    it('resolves 404 error view', function (): void {
        $this->viewFactory->shouldReceive('exists')
            ->andReturnUsing(fn ($view): bool => $view === 'errors.404');

        $result = $this->resolver->resolveErrorView(new NotFoundHttpException('Not found'), Request::create('/test-path'), 404);

        expect($result)->toBe('errors.404');
    });

    it('returns null when no view exists', function (): void {
        $this->viewFactory->shouldReceive('exists')->andReturn(false);

        $result = $this->resolver->resolveErrorView(new HttpException(500, 'Server error'), Request::create('/test-path'), 500);

        expect($result)->toBeNull();
    });

    it('tries fallback views for error categories', function (): void {
        $this->viewFactory->shouldReceive('exists')
            ->andReturnUsing(fn ($view): bool => $view === 'errors.4xx');

        $result = $this->resolver->resolveErrorView(new HttpException(403, 'Forbidden'), Request::create('/test-path'), 403);

        expect($result)->toBe('errors.4xx');
    });

    it('converts exception class to view name', function (): void {
        $this->viewFactory->shouldReceive('exists')
            ->andReturnUsing(fn ($view): bool => $view === 'errors.not-found-http');

        $result = $this->resolver->resolveErrorView(new NotFoundHttpException('Not found'), Request::create('/test-path'), 404);

        expect($result)->toBe('errors.not-found-http');
    });

    it('returns empty debug info when debug disabled', function (): void {
        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('get')->with('app.debug', false)->once()->andReturn(false);

        $this->container->shouldReceive('make')->with('config')->once()->andReturn($config);

        $debugInfo = $this->resolver->getDebugInfo(404, new NotFoundHttpException('Not found'));

        expect($debugInfo)->toBeEmpty();
    });

    it('converts PascalCase to kebab-case', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('convertToKebabCase');

        expect($method->invokeArgs($this->resolver, ['NotFoundHttpException']))->toBe('not-found-http-exception');
        expect($method->invokeArgs($this->resolver, ['ServerError']))->toBe('server-error');
        expect($method->invokeArgs($this->resolver, ['Test']))->toBe('test');
        expect($method->invokeArgs($this->resolver, ['']))->toBe('');
    });

    it('removes common suffixes', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('removeCommonSuffixes');

        expect($method->invokeArgs($this->resolver, ['NotFoundException']))->toBe('NotFound');
        expect($method->invokeArgs($this->resolver, ['ServerError']))->toBe('Server');
        expect($method->invokeArgs($this->resolver, ['NotFoundHttpException']))->toBe('NotFoundHttp');
        expect($method->invokeArgs($this->resolver, ['Test']))->toBe('Test');
    });
});

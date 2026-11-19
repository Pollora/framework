<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\FileViewFinder;
use Illuminate\View\ViewFinderInterface;
use Pollora\Exceptions\Infrastructure\Services\ModuleAwareErrorViewResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PolloraTeapotException extends RuntimeException {}

class PolloraPaymentError extends RuntimeException {}

class PolloraPlain extends RuntimeException {}

describe('ModuleAwareErrorViewResolver candidates', function (): void {
    beforeEach(function (): void {
        $this->views = Mockery::mock(ViewFactory::class);
        $this->resolver = new ModuleAwareErrorViewResolver(Mockery::mock(ContainerContract::class), $this->views);

        $this->candidates = function (int $status, Throwable $exception): array {
            $asked = [];
            $this->views->shouldReceive('exists')->andReturnUsing(function (string $view) use (&$asked): bool {
                $asked[] = $view;

                return false;
            });
            $this->resolver->resolveErrorView($exception, Request::create('/'), $status);

            return $asked;
        };
    });

    afterEach(function (): void {
        Mockery::close();
    });

    it('tries the status view, then the category views, then the exception views, in that order', function (): void {
        expect(($this->candidates)(503, new PolloraTeapotException))->toBe([
            'errors.503',
            'errors.5xx',
            'errors.server-error',
            'errors.pollora-teapot-exception',
            'errors.pollora-teapot',
        ]);
    });

    it('offers the client error views for a 4xx status', function (): void {
        expect(($this->candidates)(422, new PolloraPlain))->toBe([
            'errors.422',
            'errors.4xx',
            'errors.client-error',
            'errors.pollora-plain',
        ]);
    });

    it('offers no category view for statuses outside 400 to 599', function (int $status): void {
        expect(($this->candidates)($status, new PolloraPlain))->toBe([
            'errors.'.$status,
            'errors.pollora-plain',
        ]);
    })->with([399, 600, 200]);

    it('treats 400 and 599 as the edges of the categories', function (int $status, string $category): void {
        expect(($this->candidates)($status, new PolloraPlain))->toContain($category);
    })->with([
        [400, 'errors.4xx'],
        [499, 'errors.4xx'],
        [500, 'errors.5xx'],
        [599, 'errors.5xx'],
    ]);

    it('strips the Error suffix to build a second exception view name', function (): void {
        expect(($this->candidates)(402, new PolloraPaymentError))->toContain('errors.pollora-payment-error', 'errors.pollora-payment');
    });

    it('returns the first candidate that exists', function (): void {
        $this->views->shouldReceive('exists')->andReturnUsing(fn (string $view): bool => in_array($view, ['errors.server-error', 'errors.5xx'], true));

        expect($this->resolver->resolveErrorView(new HttpException(500), Request::create('/'), 500))->toBe('errors.5xx');
    });
});

describe('ModuleAwareErrorViewResolver view lookup in modules', function (): void {
    beforeEach(function (): void {
        $this->views = Mockery::mock(ViewFactory::class);
        $this->resolver = new ModuleAwareErrorViewResolver(Mockery::mock(ContainerContract::class), $this->views);
    });

    afterEach(function (): void {
        Mockery::close();
    });

    it('asks the view factory whether a view exists when it has a view finder', function (bool $exists): void {
        $this->views->shouldReceive('getFinder')->andReturn(Mockery::mock(ViewFinderInterface::class));
        $this->views->shouldReceive('exists')->with('errors.custom')->andReturn($exists);

        expect($this->resolver->viewExistsInModules('errors.custom'))->toBe($exists);
    })->with([true, false]);

    it('reports no view when the finder is not a view finder', function (): void {
        $this->views->shouldReceive('getFinder')->andReturn(null);
        $this->views->shouldNotReceive('exists');

        expect($this->resolver->viewExistsInModules('errors.custom'))->toBeFalse();
    });

    it('reports no view when looking it up fails', function (): void {
        $this->views->shouldReceive('getFinder')->andThrow(new RuntimeException('broken'));

        expect($this->resolver->viewExistsInModules('errors.custom'))->toBeFalse();
    });
});

describe('ModuleAwareErrorViewResolver debug information', function (): void {
    beforeEach(function (): void {
        $this->previousContainer = Container::getInstance();
        Container::getInstance()->instance('request', Request::create('/debug'));

        $this->views = Mockery::mock(ViewFactory::class);
        $this->config = new Repository(['app' => ['debug' => true]]);
        $this->container = Mockery::mock(ContainerContract::class);
        $this->container->shouldReceive('make')->with('config')->andReturn($this->config);

        $this->resolver = new ModuleAwareErrorViewResolver($this->container, $this->views);
    });

    afterEach(function (): void {
        Mockery::close();
        $this->previousContainer->forgetInstance('request');
    });

    it('lists existing and missing candidates with the registered view paths', function (): void {
        $this->views->shouldReceive('getFinder')->andReturn(new FileViewFinder(new Filesystem, ['/app/views', '/module/views']));
        $this->views->shouldReceive('exists')->andReturnUsing(fn (string $view): bool => in_array($view, ['errors.404', 'errors.4xx'], true));

        $info = $this->resolver->getDebugInfo(404, new NotFoundHttpException('gone'));

        expect($info)->toBe([
            'status_code' => 404,
            'exception_class' => NotFoundHttpException::class,
            'view_candidates' => [
                'errors.404',
                'errors.4xx',
                'errors.client-error',
                'errors.not-found-http-exception',
                'errors.not-found-http',
            ],
            'existing_views' => ['errors.404', 'errors.4xx'],
            'missing_views' => ['errors.client-error', 'errors.not-found-http-exception', 'errors.not-found-http'],
            'registered_view_paths' => ['/app/views', '/module/views'],
            'resolved_view' => 'errors.404',
        ]);
    });

    it('gives no view paths when the finder cannot list them', function (): void {
        $this->views->shouldReceive('getFinder')->andReturn(Mockery::mock(ViewFinderInterface::class));
        $this->views->shouldReceive('exists')->andReturn(false);

        $info = $this->resolver->getDebugInfo(500, new PolloraPlain);

        expect($info['registered_view_paths'])->toBe([])
            ->and($info['existing_views'])->toBe([])
            ->and($info['resolved_view'])->toBeNull();
    });

    it('gives nothing when the configuration cannot be read', function (): void {
        $container = Mockery::mock(ContainerContract::class);
        $container->shouldReceive('make')->with('config')->andThrow(new RuntimeException('no config'));

        $resolver = new ModuleAwareErrorViewResolver($container, $this->views);

        expect($resolver->getDebugInfo(404, new NotFoundHttpException('gone')))->toBe([]);
    });
});

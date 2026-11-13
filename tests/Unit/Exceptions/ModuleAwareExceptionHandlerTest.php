<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\FileViewFinder;
use Pollora\Exceptions\Infrastructure\Handlers\ModuleAwareExceptionHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds an application with the bindings the handler reads, and restores the
 * global container afterwards (Application registers itself as the instance).
 *
 * @param  array<string, mixed>  $config
 */
function pollora_handler_app(array $config = [], bool $withViews = true): Application
{
    $app = new Application(sys_get_temp_dir());
    $app->instance('config', new Repository($config));

    if ($withViews) {
        $views = Mockery::mock(ViewFactory::class);
        $app->instance('view', $views);
        $app->instance(ResponseFactoryContract::class, new ResponseFactory($views, Mockery::mock(Redirector::class)));
    }

    return $app;
}

function pollora_html_request(string $url = '/missing'): Request
{
    return Request::create($url, 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html', 'HTTP_USER_AGENT' => 'PolloraBrowser/1.0', 'REMOTE_ADDR' => '203.0.113.9']);
}

function pollora_json_request(): Request
{
    return Request::create('/api', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
}

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
});

afterEach(function (): void {
    Mockery::close();
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
    Container::setInstance($this->previousContainer);
});

describe('ModuleAwareExceptionHandler rendering', function (): void {
    it('renders an HTTP exception with the module view and its status and headers', function (): void {
        $app = pollora_handler_app();
        $rendered = [];
        $app->make('view')->shouldReceive('exists')->andReturnUsing(fn (string $view): bool => $view === 'errors.404');
        $app->make('view')->shouldReceive('make')->andReturnUsing(function (string $view, array $data) use (&$rendered): View {
            $rendered = ['view' => $view, 'data' => $data];

            return Mockery::mock(View::class, ['render' => '<h1>Module 404</h1>']);
        });

        $exception = new NotFoundHttpException('No such page', null, 0, ['X-Reason' => 'module']);
        $response = (new ModuleAwareExceptionHandler($app))->render(pollora_html_request('/missing?a=1'), $exception);

        expect($response->getStatusCode())->toBe(404)
            ->and($response->getContent())->toBe('<h1>Module 404</h1>')
            ->and($response->headers->get('X-Reason'))->toBe('module')
            ->and($rendered['view'])->toBe('errors.404')
            ->and($rendered['data']['exception'])->toBe($exception)
            ->and($rendered['data']['statusCode'])->toBe(404)
            ->and($rendered['data']['request'])->toBe([
                'url' => 'http://localhost/missing',
                'method' => 'GET',
                'userAgent' => 'PolloraBrowser/1.0',
                'ip' => '203.0.113.9',
            ])
            ->and($rendered['data'])->not->toHaveKey('debug');
    });

    it('hides the exception message and gives a status specific one outside debug mode', function (int $status, string $message): void {
        $app = pollora_handler_app(['app' => ['debug' => false]]);
        $data = [];
        $app->make('view')->shouldReceive('exists')->andReturn(true);
        $app->make('view')->shouldReceive('make')->andReturnUsing(function (string $view, array $viewData) use (&$data): View {
            $data = $viewData;

            return Mockery::mock(View::class, ['render' => 'x']);
        });

        (new ModuleAwareExceptionHandler($app))->render(pollora_html_request(), new HttpException($status, 'secret internal detail'));

        expect($data['message'])->toBe($message);
    })->with([
        [404, 'The requested page could not be found.'],
        [403, 'Access to this resource is forbidden.'],
        [401, 'Authentication is required to access this resource.'],
        [500, 'An internal server error occurred.'],
        [503, 'The service is temporarily unavailable.'],
        [418, 'An error occurred while processing your request.'],
    ]);

    it('shows the exception message and the view debugging data in debug mode', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => true]]);
        $app->instance('request', pollora_html_request());

        $data = [];
        $finder = new FileViewFinder(new Filesystem, ['/views/app']);
        $app->make('view')->shouldReceive('getFinder')->andReturn($finder);
        $app->make('view')->shouldReceive('exists')->andReturnUsing(fn (string $view): bool => $view === 'errors.404');
        $app->make('view')->shouldReceive('make')->andReturnUsing(function (string $view, array $viewData) use (&$data): View {
            $data = $viewData;

            return Mockery::mock(View::class, ['render' => 'x']);
        });

        (new ModuleAwareExceptionHandler($app))->render(pollora_html_request(), new NotFoundHttpException('No such page'));

        expect($data['message'])->toBe('No such page')
            ->and($data['debug']['status_code'])->toBe(404)
            ->and($data['debug']['exception_class'])->toBe(NotFoundHttpException::class)
            ->and($data['debug']['existing_views'])->toBe(['errors.404'])
            ->and($data['debug']['resolved_view'])->toBe('errors.404')
            ->and($data['debug']['registered_view_paths'])->toBe(['/views/app']);
    });

    it('falls back to the status message in debug mode when the exception has no message', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => true]]);
        $app->instance('request', pollora_html_request());

        $data = [];
        $app->make('view')->shouldReceive('getFinder')->andReturn(new FileViewFinder(new Filesystem, []));
        $app->make('view')->shouldReceive('exists')->andReturn(true);
        $app->make('view')->shouldReceive('make')->andReturnUsing(function (string $view, array $viewData) use (&$data): View {
            $data = $viewData;

            return Mockery::mock(View::class, ['render' => 'x']);
        });

        (new ModuleAwareExceptionHandler($app))->render(pollora_html_request(), new HttpException(403));

        expect($data['message'])->toBe('Access to this resource is forbidden.');
    });

    it('lets Laravel render exceptions that are not HTTP exceptions', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => false]]);
        $app->make('view')->shouldNotReceive('exists');

        $response = (new ModuleAwareExceptionHandler($app))->render(pollora_json_request(), new RuntimeException('boom'));

        expect($response->getStatusCode())->toBe(500)
            ->and(json_decode($response->getContent(), true))->toBe(['message' => 'Server Error']);
    });

    it('lets Laravel render an HTTP exception when no module view exists', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => false]]);
        $app->make('view')->shouldReceive('exists')->andReturn(false);

        Facade::setFacadeApplication($app); // Laravel's HTML fallback uses the facades
        $app->make('view')->shouldReceive('replaceNamespace')->andReturnNull();
        $response = (new ModuleAwareExceptionHandler($app))->render(pollora_html_request(), new NotFoundHttpException('gone'));

        expect($response->getStatusCode())->toBe(404);
    });

    it('logs a failure to render the module view and falls back to Laravel', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => false]]);
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with('Failed to render module error view: view crashed');
        $app->instance(LoggerInterface::class, $logger);
        $crashed = false;
        // Crashes on the module lookup only: Laravel's own fallback asks the same factory afterwards.
        $app->make('view')->shouldReceive('exists')->andReturnUsing(function () use (&$crashed): bool {
            if (! $crashed) {
                $crashed = true;

                throw new RuntimeException('view crashed');
            }

            return false;
        });

        Facade::setFacadeApplication($app); // Laravel's HTML fallback uses the facades
        $app->make('view')->shouldReceive('replaceNamespace')->andReturnNull();
        $response = (new ModuleAwareExceptionHandler($app))->render(pollora_html_request(), new NotFoundHttpException('gone'));

        expect($response->getStatusCode())->toBe(404);
    });

    it('survives a rendering failure when no logger is available', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => false]]);
        $crashed = false;
        // Crashes on the module lookup only: Laravel's own fallback asks the same factory afterwards.
        $app->make('view')->shouldReceive('exists')->andReturnUsing(function () use (&$crashed): bool {
            if (! $crashed) {
                $crashed = true;

                throw new RuntimeException('view crashed');
            }

            return false;
        });

        Facade::setFacadeApplication($app); // Laravel's HTML fallback uses the facades
        $app->make('view')->shouldReceive('replaceNamespace')->andReturnNull();
        $response = (new ModuleAwareExceptionHandler($app))->render(pollora_html_request(), new NotFoundHttpException('gone'));

        expect($response->getStatusCode())->toBe(404);
    });

    it('logs when the view resolver cannot be built and still works', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => false]], withViews: false);
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->withArgs(
            fn (string $message): bool => str_starts_with($message, 'Failed to initialize ModuleAwareErrorViewResolver: ')
        );
        $app->instance(LoggerInterface::class, $logger);

        $handler = new ModuleAwareExceptionHandler($app);

        // The logger expectation above is verified when the test ends; the handler stays usable.
        expect($handler->shouldReport(new RuntimeException('boom')))->toBeTrue();
    });
});

describe('ModuleAwareExceptionHandler content negotiation', function (): void {
    it('decides between HTML and JSON the way Laravel does', function (?string $accept, bool $ajax, bool $expected): void {
        $handler = new ModuleAwareExceptionHandler(pollora_handler_app());
        $method = new ReflectionMethod($handler, 'shouldRenderHtmlResponse');

        // Request::create() adds a browser-like Accept header by default: set or remove it explicitly.
        $request = Request::create('/page', 'GET', [], [], [], $ajax ? ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : []);
        $accept === null ? $request->headers->remove('Accept') : $request->headers->set('Accept', $accept);

        expect($method->invoke($handler, $request))->toBe($expected);
    })->with([
        'Accept JSON' => ['application/json', false, false],
        'Accept HTML' => ['text/html', false, true],
        'no Accept header' => [null, false, true],
        'AJAX without a specific Accept' => ['*/*', true, false],
    ]);

    it('does not try the module views for an HTTP exception on a JSON request', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => false]]);
        $app->make('view')->shouldReceive('exists')->andReturn(true);
        $app->make('view')->shouldReceive('make')->andReturn(Mockery::mock(View::class, ['render' => '<h1>Module 404</h1>']));

        $response = (new ModuleAwareExceptionHandler($app))->render(pollora_json_request(), new NotFoundHttpException('gone'));

        expect($response->getStatusCode())->toBe(404)
            ->and($response->headers->get('Content-Type'))->toContain('application/json')
            ->and(json_decode($response->getContent(), true))->toBe(['message' => 'gone']);
    });

    it('renders the module view for an HTTP exception on a request without Accept header', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => false]]);
        $app->make('view')->shouldReceive('exists')->andReturnUsing(fn (string $view): bool => $view === 'errors.404');
        $app->make('view')->shouldReceive('make')->andReturn(Mockery::mock(View::class, ['render' => '<h1>Module 404</h1>']));
        $request = Request::create('/missing');
        $request->headers->remove('Accept');

        $response = (new ModuleAwareExceptionHandler($app))->render($request, new NotFoundHttpException('gone'));

        expect($response->getStatusCode())->toBe(404)
            ->and($response->getContent())->toBe('<h1>Module 404</h1>');
    });
});

describe('ModuleAwareExceptionHandler reporting', function (): void {
    it('lets a module silence or force the reporting of an exception class', function (string $class, bool $report, bool $expected): void {
        $app = pollora_handler_app(['pollora' => ['exceptions' => ['reporting' => [
            ['exception' => RuntimeException::class, 'report' => false],
            ['exception' => NotFoundHttpException::class, 'report' => true],
        ]]]], withViews: false);

        $exception = new $class('x');

        expect((new ModuleAwareExceptionHandler($app))->shouldReport($exception))->toBe($expected);
    })->with([
        'silenced' => [RuntimeException::class, false, false],
        'forced although Laravel ignores it' => [NotFoundHttpException::class, true, true],
    ]);

    it('keeps the default decision for exceptions no rule mentions', function (): void {
        $app = pollora_handler_app(['pollora' => ['exceptions' => ['reporting' => [
            ['exception' => RuntimeException::class, 'report' => false],
        ]]]], withViews: false);
        $handler = new ModuleAwareExceptionHandler($app);

        expect($handler->shouldReport(new LogicException('x')))->toBeTrue()
            ->and($handler->shouldReport(new NotFoundHttpException('x')))->toBeFalse();
    });

    it('keeps the default decision for a rule that does not say whether to report', function (): void {
        $app = pollora_handler_app(['pollora' => ['exceptions' => ['reporting' => [
            ['exception' => RuntimeException::class],
        ]]]], withViews: false);

        expect((new ModuleAwareExceptionHandler($app))->shouldReport(new RuntimeException('x')))->toBeTrue();
    });

    it('keeps the default decision when there are no rules or no configuration', function (array $config): void {
        $app = pollora_handler_app($config, withViews: false);
        $handler = new ModuleAwareExceptionHandler($app);

        expect($handler->shouldReport(new RuntimeException('x')))->toBeTrue()
            ->and($handler->shouldReport(new NotFoundHttpException('x')))->toBeFalse();
    })->with([
        'empty rules' => [['pollora' => ['exceptions' => ['reporting' => []]]]],
        'no rules key' => [[]],
    ]);

    it('keeps the default decision when the configuration is not available', function (): void {
        $app = new Application(sys_get_temp_dir());

        expect((new ModuleAwareExceptionHandler($app))->shouldReport(new RuntimeException('x')))->toBeTrue();
    });
});

describe('ModuleAwareExceptionHandler debug information', function (): void {
    it('refuses to give debug information outside debug mode', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => false]]);

        expect((new ModuleAwareExceptionHandler($app))->getErrorViewDebugInfo(new RuntimeException('x'), pollora_html_request()))
            ->toBe(['debug' => false, 'message' => 'Debug mode is disabled']);
    });

    it('reports when the configuration is not available', function (): void {
        $app = new Application(sys_get_temp_dir());

        expect((new ModuleAwareExceptionHandler($app))->getErrorViewDebugInfo(new RuntimeException('x'), pollora_html_request()))
            ->toBe(['debug' => false, 'message' => 'Config not available']);
    });

    it('reports when the view resolver is not available', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => true]], withViews: false);

        expect((new ModuleAwareExceptionHandler($app))->getErrorViewDebugInfo(new RuntimeException('x'), pollora_html_request()))
            ->toBe(['error' => 'ModuleAwareErrorViewResolver not available']);
    });

    it('describes the candidates for a status 500 when the exception is not an HTTP exception', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => true]]);
        $app->instance('request', pollora_html_request());
        $app->make('view')->shouldReceive('getFinder')->andReturn(new FileViewFinder(new Filesystem, ['/views']));
        $app->make('view')->shouldReceive('exists')->andReturnUsing(fn (string $view): bool => $view === 'errors.5xx');

        $info = (new ModuleAwareExceptionHandler($app))->getErrorViewDebugInfo(new RuntimeException('x'), pollora_html_request());

        expect($info['status_code'])->toBe(500)
            ->and($info['exception_class'])->toBe(RuntimeException::class)
            ->and($info['existing_views'])->toBe(['errors.5xx'])
            ->and($info['resolved_view'])->toBe('errors.5xx');
    });

    it('uses the status code of an HTTP exception', function (): void {
        $app = pollora_handler_app(['app' => ['debug' => true]]);
        $app->instance('request', pollora_html_request());
        $app->make('view')->shouldReceive('getFinder')->andReturn(new FileViewFinder(new Filesystem, []));
        $app->make('view')->shouldReceive('exists')->andReturn(false);

        $info = (new ModuleAwareExceptionHandler($app))->getErrorViewDebugInfo(new HttpException(503), pollora_html_request());

        expect($info['status_code'])->toBe(503)
            ->and($info['view_candidates'])->toContain('errors.503', 'errors.5xx')
            ->and($info['resolved_view'])->toBeNull();
    });
});

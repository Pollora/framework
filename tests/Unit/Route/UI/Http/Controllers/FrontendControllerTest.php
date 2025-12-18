<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Pollora\Route\UI\Http\Controllers\FrontendController;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../../../../helpers.php';

beforeEach(function () {
    setupWordPressMocks();

    // Ensure our custom container is set up for each test
    $container = \Illuminate\Container\Container::getInstance();
    if (! method_exists($container, 'abort')) {
        $customContainer = new class($container) extends \Illuminate\Container\Container
        {
            private $original;

            public function __construct($original)
            {
                $this->original = $original;
                // Copy all properties
                if (property_exists($original, 'bindings')) {
                    $this->bindings = $original->bindings ?? [];
                }
                if (property_exists($original, 'instances')) {
                    $this->instances = $original->instances ?? [];
                }
                if (property_exists($original, 'aliases')) {
                    $this->aliases = $original->aliases ?? [];
                }
                if (property_exists($original, 'abstractAliases')) {
                    $this->abstractAliases = $original->abstractAliases ?? [];
                }
            }

            public function publicPath($path = '')
            {
                return '/var/www/html/public'.($path ? '/'.ltrim($path, '/') : '');
            }

            public function abort($code = 404, $message = '')
            {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException($code, $message);
            }

            // Delegate all other method calls to original container if they exist
            public function __call($method, $arguments)
            {
                if (method_exists($this->original, $method)) {
                    return call_user_func_array([$this->original, $method], $arguments);
                }

                return parent::__call($method, $arguments);
            }
        };

        \Illuminate\Container\Container::setInstance($customContainer);

        // Bind ResponseFactory
        if (! $customContainer->bound(\Illuminate\Contracts\Routing\ResponseFactory::class)) {
            $customContainer->bind(\Illuminate\Contracts\Routing\ResponseFactory::class, function () {
                return new class implements \Illuminate\Contracts\Routing\ResponseFactory
                {
                    public function make($content = '', $status = 200, array $headers = [])
                    {
                        return new \Illuminate\Http\Response($content, $status, $headers);
                    }

                    public function view($view, $data = [], $status = 200, array $headers = [])
                    {
                        return new \Illuminate\Http\Response($view, $status, $headers);
                    }

                    public function json($data = [], $status = 200, array $headers = [], $options = 0)
                    {
                        return new \Illuminate\Http\JsonResponse($data, $status, $headers, $options);
                    }

                    public function jsonp($callback, $data = [], $status = 200, array $headers = [], $options = 0)
                    {
                        return $this->json($data, $status, $headers, $options)->setCallback($callback);
                    }

                    public function stream($callback, $status = 200, array $headers = [])
                    {
                        return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, $status, $headers);
                    }

                    public function streamDownload($callback, $name = null, array $headers = [], $disposition = 'attachment')
                    {
                        return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, 200, array_merge($headers, [
                            'Content-Disposition' => "{$disposition}; filename=\"{$name}\"",
                        ]));
                    }

                    public function download($file, $name = null, array $headers = [], $disposition = 'attachment')
                    {
                        return new \Symfony\Component\HttpFoundation\BinaryFileResponse($file, 200, $headers, true, $disposition);
                    }

                    public function file($file, array $headers = [])
                    {
                        return new \Symfony\Component\HttpFoundation\BinaryFileResponse($file, 200, $headers);
                    }

                    public function redirectTo($path, $status = 302, $headers = [], $secure = null)
                    {
                        return new \Illuminate\Http\RedirectResponse($path, $status, $headers);
                    }

                    public function redirectToRoute($route, $parameters = [], $status = 302, $headers = [])
                    {
                        return $this->redirectTo($route, $status, $headers);
                    }

                    public function redirectToAction($action, $parameters = [], $status = 302, $headers = [])
                    {
                        return $this->redirectTo($action, $status, $headers);
                    }

                    public function redirectGuest($path, $status = 302, $headers = [], $secure = null)
                    {
                        return $this->redirectTo($path, $status, $headers);
                    }

                    public function redirectToIntended($default = '/', $status = 302, $headers = [], $secure = null)
                    {
                        return $this->redirectTo($default, $status, $headers);
                    }

                    public function noContent($status = 204, array $headers = [])
                    {
                        return new \Illuminate\Http\Response('', $status, $headers);
                    }

                    public function streamJson($data, $status = 200, $headers = [], $encodingOptions = 15)
                    {
                        return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($data, $encodingOptions) {
                            echo json_encode($data, $encodingOptions);
                        }, $status, array_merge($headers, ['Content-Type' => 'application/json']));
                    }
                };
            });
        }
    }

    $this->templateFinder = Mockery::mock(TemplateFinderInterface::class);
    $this->controller = new FrontendController($this->templateFinder);
});

afterEach(function () {
    Mockery::close();
});

describe('FrontendController', function () {
    it('aborts when themes disabled', function () {
        setWordPressFunction('wp_using_themes', fn () => false);
        $request = Request::create('/test');

        expect(fn () => $this->controller->handle($request))
            ->toThrow(HttpException::class, 'Themes are disabled');
    });

    it('renders blade view when available', function () {
        setWordPressFunction('wp_using_themes', fn () => true);
        setWordPressFunction('is_page', fn () => true);
        setWordPressFunction('get_page_template', fn () => '/theme/page.php');
        setWordPressFunction('apply_filters', fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with('/theme/page.php')
            ->andReturn('templates.page');

        View::shouldReceive('exists')
            ->with('templates.page')
            ->andReturn(true);
        View::shouldReceive('make')
            ->with('templates.page')
            ->andReturn('<html>Blade page content</html>');

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getContent())->toBe('<html>Blade page content</html>');
    });

    it('falls back to php template', function () {
        $templatePath = __DIR__.'/test-template.php';
        setWordPressFunction('wp_using_themes', fn () => true);
        setWordPressFunction('is_page', fn () => true);
        setWordPressFunction('get_page_template', fn () => $templatePath);
        setWordPressFunction('apply_filters', fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with($templatePath)
            ->andReturn(null);

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getContent())->toBe('This is a PHP template');
    });

    it('throws 404 when no template', function () {
        setWordPressFunction('wp_using_themes', fn () => true);

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

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with('')
            ->andReturn(null);

        $request = Request::create('/test');

        expect(fn () => $this->controller->handle($request))
            ->toThrow(HttpException::class);
    });
});

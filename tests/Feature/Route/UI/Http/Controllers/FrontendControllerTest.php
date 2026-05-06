<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Pollora\Route\UI\Http\Controllers\FrontendController;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

beforeEach(function (): void {
    $this->templateFinder = Mockery::mock(TemplateFinderInterface::class);
    $this->controller = new FrontendController($this->templateFinder);
});

describe('FrontendController', function (): void {
    it('returns 404 when themes disabled', function (): void {
        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(false);

        View::shouldReceive('exists')->andReturn(false);
        View::shouldReceive('replaceNamespace')->andReturnNull();
        View::shouldReceive('addNamespace')->andReturnNull();

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatusCode())->toBe(404);
    });

    it('renders blade view when available', function (): void {
        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(true);
        Brain\Monkey\Functions\when('is_page')->justReturn(true);
        Brain\Monkey\Functions\when('is_404')->justReturn(false);
        Brain\Monkey\Functions\when('get_page_template')->justReturn('/theme/page.php');
        Brain\Monkey\Functions\when('apply_filters')->alias(fn ($filter, $value) => $value);

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
        expect($response->getStatusCode())->toBe(200);
    });

    it('falls back to php template', function (): void {
        $templatePath = __DIR__.'/test-template.php';
        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(true);
        Brain\Monkey\Functions\when('is_page')->justReturn(true);
        Brain\Monkey\Functions\when('is_404')->justReturn(false);
        Brain\Monkey\Functions\when('get_page_template')->justReturn($templatePath);
        Brain\Monkey\Functions\when('apply_filters')->alias(fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with($templatePath)
            ->andReturn(null);

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getContent())->toBe('This is a PHP template');
    });

    it('returns 404 response when no template found', function (): void {
        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(true);

        Brain\Monkey\Functions\stubs([
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

        Brain\Monkey\Functions\when('get_index_template')->justReturn('');
        Brain\Monkey\Functions\when('apply_filters')->alias(fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with('')
            ->andReturn(null);

        View::shouldReceive('exists')->andReturn(false);
        View::shouldReceive('replaceNamespace')->andReturnNull();
        View::shouldReceive('addNamespace')->andReturnNull();

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatusCode())->toBe(404);
    });

    it('renders Laravel error page when 404 with index fallback', function (): void {
        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(true);

        Brain\Monkey\Functions\stubs([
            'is_embed' => false,
            'is_404' => true,
            'is_search' => false,
            'is_front_page' => false,
            'is_home' => false,
            'is_privacy_policy' => false,
            'is_post_type_archive' => false,
            'is_tax' => false,
            'is_attachment' => false,
            'is_single' => false,
            'is_page' => false,
            'is_singular' => false,
            'is_category' => false,
            'is_tag' => false,
            'is_author' => false,
            'is_date' => false,
            'is_archive' => false,
        ]);

        Brain\Monkey\Functions\when('get_404_template')->justReturn('');
        Brain\Monkey\Functions\when('get_index_template')->justReturn('/theme/index.php');
        Brain\Monkey\Functions\when('apply_filters')->alias(fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with('/theme/index.php')
            ->andReturn('index');

        View::shouldReceive('exists')
            ->with('errors.404')
            ->andReturn(false);
        View::shouldReceive('replaceNamespace')->andReturnNull();
        View::shouldReceive('addNamespace')->andReturnNull();
        View::shouldReceive('exists')
            ->with('errors::404')
            ->andReturn(false);

        $request = Request::create('/nonexistent');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatusCode())->toBe(404);
        expect($response->getContent())->toBe('Not Found');
    });
});
